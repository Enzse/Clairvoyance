<?php

namespace Controllers;

use Core\Auth;
use Core\HttpException;
use Core\Request;
use Core\Response;
use Services\ImportService;

/**
 * Multipart uploads with fields: file (the CSV), mapping (JSON, see ImportService),
 * and for commit, optionally skip_invalid=1.
 */
class ImportController
{
    private Auth $auth;
    private ImportService $imports;

    public function __construct(Auth $auth, ImportService $imports)
    {
        $this->auth = $auth;
        $this->imports = $imports;
    }

    public function preview(Request $request): array
    {
        $this->auth->requireUser();
        return $this->imports->preview($this->uploadedPath($request), $this->mapping($request));
    }

    public function commit(Request $request): Response
    {
        $this->auth->requireUser();
        $skipInvalid = filter_var($request->input('skip_invalid', false), FILTER_VALIDATE_BOOLEAN);
        $name = (string)($request->files['file']['name'] ?? 'upload.csv');
        return Response::created($this->imports->commit($this->uploadedPath($request), $name, $this->mapping($request), $skipInvalid));
    }

    public function list(): array
    {
        $this->auth->requireAdmin();
        return $this->imports->recent();
    }

    private function uploadedPath(Request $request): string
    {
        $file = $request->files['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw HttpException::badRequest('Attach the CSV file as "file"');
        }
        return $file['tmp_name'];
    }

    private function mapping(Request $request): array
    {
        $mapping = $request->input('mapping');
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true);
        }
        if (!is_array($mapping)) {
            throw HttpException::badRequest('Send the column mapping as JSON in "mapping"');
        }
        return $mapping;
    }
}
