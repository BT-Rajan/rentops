<?php
declare(strict_types=1);

namespace App\Controllers;

use App\DB;

class UploadController extends BaseController
{
    private const UPLOAD_DIR     = ROOT . '/public/uploads/id_proofs/';
    private const MAX_SIZE       = 5 * 1024 * 1024; // 5 MB
    private const ALLOWED_TYPES  = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    private const ALLOWED_EXT    = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public function uploadIdProof(array $params = []): void
    {
        $this->verifyCsrf();

        $tenantId = $params['id'] ?? '';
        $tenant   = DB::row('SELECT * FROM tenants WHERE id = ?', [$tenantId]);
        if (!$tenant) { $this->json(['error' => 'Tenant not found.'], 404); return; }

        $file = $_FILES['id_proof'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Upload failed. ' . $this->uploadError($file['error'] ?? -1)], 400);
            return;
        }

        // Size check
        if ($file['size'] > self::MAX_SIZE) {
            $this->json(['error' => 'File too large. Max 5 MB.'], 400);
            return;
        }

        // MIME type check (real MIME, not just extension)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_TYPES)) {
            $this->json(['error' => 'Invalid file type. Allowed: JPG, PNG, WEBP, PDF.'], 400);
            return;
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT)) {
            $this->json(['error' => 'Invalid extension.'], 400);
            return;
        }

        // Delete old proof if exists
        if ($tenant['id_proof_file']) {
            $old = self::UPLOAD_DIR . basename($tenant['id_proof_file']);
            if (file_exists($old)) @unlink($old);
        }

        // Store with non-guessable filename
        $filename = $tenantId . '_' . time() . '.' . $ext;
        $dest     = self::UPLOAD_DIR . $filename;

        if (!is_dir(self::UPLOAD_DIR)) mkdir(self::UPLOAD_DIR, 0750, true);

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->json(['error' => 'Could not save file.'], 500);
            return;
        }

        // Save relative path in DB
        $webPath = '/uploads/id_proofs/' . $filename;
        DB::update('tenants', ['id_proof_file' => $webPath], 'id = ?', [$tenantId]);

        $this->json(['success' => true, 'path' => $webPath, 'filename' => $filename]);
    }

    public function deleteIdProof(array $params = []): void
    {
        $this->verifyCsrf();
        $tenant = DB::row('SELECT * FROM tenants WHERE id = ?', [$params['id'] ?? '']);
        if (!$tenant) { $this->json(['error' => 'Not found.'], 404); return; }

        if ($tenant['id_proof_file']) {
            $path = self::UPLOAD_DIR . basename($tenant['id_proof_file']);
            if (file_exists($path)) @unlink($path);
            DB::update('tenants', ['id_proof_file' => null], 'id = ?', [$tenant['id']]);
        }

        $this->json(['success' => true]);
    }

    private function uploadError(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds size limit.',
            UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE   => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR=> 'Server temp directory missing.',
            UPLOAD_ERR_CANT_WRITE=> 'Failed to write to disk.',
            default              => 'Unknown upload error.',
        };
    }
}
