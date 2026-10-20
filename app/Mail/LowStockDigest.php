<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * The owner's daily e-mail: products below their minimum, per warehouse (same rows as the dashboard).
 */
class LowStockDigest extends Mailable
{
    /** Rows listed in the mail; the report has the rest. */
    private const LIMIT = 30;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows  from ReportService::stock() (split by warehouse)
     */
    public function __construct(public readonly Company $company, public readonly Collection $rows) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "تنبيه: {$this->rows->count()} مواد منخفضة بمخزون {$this->company->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.low-stock', with: [
            'listed' => $this->rows->take(self::LIMIT),
            'more' => max(0, $this->rows->count() - self::LIMIT),
            'reportUrl' => route('reports.show', ['report' => 'low-stock', 'split' => 1]),
            'settingsUrl' => route('settings.edit'),
        ]);
    }
}
