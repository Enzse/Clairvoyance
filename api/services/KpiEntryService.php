<?php

namespace Services;

use Models\Kpi;
use Models\KpiEntry;

class KpiEntryService
{
	private Kpi $kpis;
	private KpiEntry $entries;

	public function __construct(Kpi $kpis, KpiEntry $entries)
	{
		$this->kpis = $kpis;
		$this->entries = $entries;
	}

	/**
	 * Admins and KPI owners have full access. Other users get access through a
	 * dashboard that shows the KPI: any level can read, only editor/owner can write.
	 */
	private function ensureAccess(array $currentUser, int $kpiId, bool $write): void
	{
		$kpi = $this->kpis->findById($kpiId);
		if (!$kpi) throw new \Exception('KPI not found', 404);

		if ($currentUser['role'] === 'admin' || (int)$kpi['user_id'] === (int)$currentUser['id']) {
			return;
		}

		$level = $this->kpis->getDashboardPermissionLevel($kpiId, (int)$currentUser['id']);
		if ($level !== null && (!$write || in_array($level, ['editor', 'owner'], true))) {
			return;
		}

		throw new \Exception('Access denied', 403);
	}

	public function add(array $currentUser, int $kpiId, string $date, $value): array
	{
		$this->ensureAccess($currentUser, $kpiId, true);

		if (!$this->entries->validateDate($date)) throw new \Exception('Invalid date (YYYY-MM-DD)', 400);
		if (!$this->entries->validateValue($value)) throw new \Exception('Invalid value', 400);
		// Removed: Allow multiple entries for the same date
		// if ($this->entries->hasEntryForDate($kpiId, $date)) throw new \Exception('Entry for date already exists', 409);

		return $this->entries->create($kpiId, $date, (float)$value);
	}

	public function bulkInsert(array $currentUser, int $kpiId, array $rows): array
	{
		$this->ensureAccess($currentUser, $kpiId, true);

		$normalized = [];
		foreach ($rows as $row) {
			$errors = $this->entries->validateEntry($row);
			if (!empty($errors)) {
				return ['success' => false, 'error' => implode('; ', $errors), 'code' => 400];
			}
			// Removed: Allow multiple entries for the same date
			// if ($this->entries->hasEntryForDate($kpiId, $row['date'])) {
			// 	return ['success' => false, 'error' => "Entry exists for {$row['date']}", 'code' => 409];
			// }
			$normalized[] = ['date' => $row['date'], 'value' => (float)$row['value']];
		}
		return $this->entries->bulkInsert($kpiId, $normalized);
	}

	public function query(array $currentUser, int $kpiId, ?string $startDate = null, ?string $endDate = null): array
	{
		$this->ensureAccess($currentUser, $kpiId, false);
		return $this->entries->findByKpiId($kpiId, $startDate, $endDate);
	}

	public function aggregate(array $currentUser, int $kpiId, string $type, ?string $startDate = null, ?string $endDate = null): ?array
	{
		$this->ensureAccess($currentUser, $kpiId, false);
		return $this->entries->getAggregateValue($kpiId, $type, $startDate, $endDate);
	}

	public function update(array $currentUser, int $entryId, array $data): bool
	{
		// First, get the entry to find the KPI ID for ownership check
		$entry = $this->entries->findById($entryId);
		if (!$entry) {
			throw new \Exception('KPI entry not found', 404);
		}
		
		// Check write access through the KPI
		$this->ensureAccess($currentUser, (int)$entry['kpi_id'], true);

		// Validate the data
		if (isset($data['date']) && !$this->entries->validateDate($data['date'])) {
			throw new \Exception('Invalid date (YYYY-MM-DD)', 400);
		}
		if (isset($data['value']) && !$this->entries->validateValue($data['value'])) {
			throw new \Exception('Invalid value', 400);
		}
		
		// Removed: Allow multiple entries for the same date
		// Check if the new date conflicts with another entry (if date is being changed)
		// if (isset($data['date']) && $data['date'] !== $entry['date']) {
		// 	if ($this->entries->hasEntryForDate((int)$entry['kpi_id'], $data['date'])) {
		// 		throw new \Exception('Entry for date already exists', 409);
		// 	}
		// }
		
		return $this->entries->update($entryId, $data);
	}

	public function delete(array $currentUser, int $entryId): bool
	{
		// First, get the entry to find the KPI ID for ownership check
		$entry = $this->entries->findById($entryId);
		if (!$entry) {
			throw new \Exception('KPI entry not found', 404);
		}
		
		// Check write access through the KPI
		$this->ensureAccess($currentUser, (int)$entry['kpi_id'], true);

		return $this->entries->delete($entryId);
	}
}