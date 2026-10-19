<?php

namespace Services;

use Core\Auth;
use Core\Validator;
use Repositories\OrganizationRepository;

/**
 * The getting-started guide for a new organisation's admins: which steps are done, which were
 * skipped, and a few counts to show what's there. Done is worked out from the data, so the
 * guide stays right however a step was completed.
 */
class SetupService
{
    public const STEPS = ['structure', 'kpis', 'targets', 'data', 'people'];
    /** KPIs and figures are what the app is for; the rest can wait */
    public const SKIPPABLE = ['structure', 'targets', 'people'];

    private Auth $auth;
    private OrganizationRepository $organizations;

    public function __construct(Auth $auth, OrganizationRepository $organizations)
    {
        $this->auth = $auth;
        $this->organizations = $organizations;
    }

    public function get(): array
    {
        $organization = $this->auth->requireAdmin();
        $row = $this->organizations->find($organization['id']);
        $facts = $this->organizations->setupFacts($organization['id']);
        $done = [
            'structure' => $facts['units'] > 0,
            'kpis' => $facts['kpis'] > 0,
            'targets' => $facts['targets'] > 0,
            'data' => $facts['kpi_values'] > 0,
            'people' => $facts['members'] > 1 || $facts['invitations'] > 0,
        ];
        $skipped = array_values(array_diff($this->skipped($row), array_keys(array_filter($done))));

        return [
            'dismissed' => $row['setup_dismissed_at'] !== null,
            'steps' => $done,
            'skipped' => $skipped,
            'skippable' => self::SKIPPABLE,
            'complete' => !array_diff(self::STEPS, [...array_keys(array_filter($done)), ...$skipped]),
            'counts' => [
                'units' => $facts['units'],
                'kpis' => $facts['kpis'],
                'targets' => $facts['targets'],
                'values' => $facts['kpi_values'],
                'members' => $facts['members'],
                'invitations' => $facts['invitations'],
            ],
        ];
    }

    /** {dismissed: true} hides the guide for everyone in the organisation; false brings it back. */
    public function dismiss(array $input): array
    {
        $organization = $this->auth->requireAdmin();
        $v = new Validator($input);
        $dismissed = $v->bool('dismissed') ?? true;
        $v->check();
        $this->organizations->setSetupDismissed($organization['id'], $dismissed);
        return $this->get();
    }

    /** {step, skipped}: skip a step that can wait, or take it back. */
    public function skip(array $input): array
    {
        $organization = $this->auth->requireAdmin();
        $v = new Validator($input);
        $step = $v->enum('step', self::SKIPPABLE, true);
        $skip = $v->bool('skipped') ?? true;
        $v->check();

        $skipped = array_diff($this->skipped($this->organizations->find($organization['id'])), [$step]);
        if ($skip) {
            $skipped[] = $step;
        }
        $this->organizations->setSetupSkipped($organization['id'], array_values(array_intersect(self::SKIPPABLE, $skipped)));
        return $this->get();
    }

    /** @return string[] */
    private function skipped(array $row): array
    {
        return array_values(array_intersect(self::SKIPPABLE, explode(',', $row['setup_skipped'])));
    }
}
