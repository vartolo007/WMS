<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Company identity and pricing settings (FR-25, FR-09).
 */
class CompanySettingsService
{
    /**
     * Saves the settings. A new logo goes to `companies/{id}/` on the public disk with a fresh name
     * (so browsers never show a cached old logo); the previous file is deleted only after the save worked.
     *
     * @param  array{name: string, primary_color: string, currency_symbol?: string|null, markup_percent: string}  $data
     */
    public function update(Company $company, array $data, ?UploadedFile $logo = null): void
    {
        $disk = Storage::disk('public');
        $oldPath = $company->logo_path;
        $newPath = null;

        try {
            DB::transaction(function () use ($company, $data, $logo, $disk, &$newPath) {
                if ($logo !== null) {
                    $newPath = $disk->putFileAs(
                        "companies/{$company->id}",
                        $logo,
                        'logo-'.Str::lower(Str::random(8)).'.'.$logo->extension(),
                    );
                    $data['logo_path'] = $newPath;
                }

                $company->update($data);
            });
        } catch (Throwable $e) {
            if ($newPath !== null) {
                $disk->delete($newPath);
            }

            throw $e;
        }

        if ($newPath !== null && $oldPath !== null) {
            $disk->delete($oldPath);
        }
    }
}
