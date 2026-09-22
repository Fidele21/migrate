<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Inspection;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Filling the City's own letter templates.
 *
 * Every letter type has its own approved .docx. The template to use is
 * looked up from config('letters.types'), keyed by the letter's own
 * letter_type. Where a type has been defined but no approved file exists
 * yet, the download refuses clearly rather than silently falling back to
 * a different letter's wording.
 */
class LetterTemplateController extends Controller
{
    private const TEMPLATE_DIR = 'templates/';

    public function download(Document $letter)
    {
        abort_unless($letter->type === 'letter', 404);

        $type = config('letters.types.' . $letter->letter_type);

        abort_if(! $type, 500,
            "The letter type \"{$letter->letter_type}\" is not defined in config/letters.php.");

        abort_if(empty($type['template']), 422,
            "No approved template has been provided yet for \"{$type['name']}\" letters. "
            . 'This letter type is defined but cannot be exported until the .docx file is '
            . 'supplied and placed in storage/app/' . self::TEMPLATE_DIR);

        $path = storage_path('app/' . self::TEMPLATE_DIR . $type['template']);

        abort_unless(is_file($path), 500,
            "The template for \"{$type['name']}\" is missing. It should be at "
            . 'storage/app/' . self::TEMPLATE_DIR . $type['template']);

        Settings::setOutputEscapingEnabled(false);

        $t = new TemplateProcessor($path);

        $set = fn (string $k, ?string $v) => $t->setValue(
            $k, htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8')
        );

        $fields = static::fields($letter);

        foreach ($fields as $key => $value) {
            if ($key === 'recommendations') {
                continue;
            }
            $set($key, (string) $value);
        }

        $this->fillAnnex($t, $fields['recommendations']);

        $entity = $letter->inspection?->entity;

        $name = 'Ibaruwa-'
              . preg_replace('/[^A-Za-z0-9]+/', '-', $entity?->name ?: 'letter')
              . '-' . ($letter->reference_number ?: $letter->id) . '.docx';

        $tmp = storage_path('app/' . uniqid('letter_', true) . '.docx');
        $t->saveAs($tmp);

        return response()->download($tmp, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /** Whether this letter type has an approved template to export against. */
    public static function isExportable(string $letterType): bool
    {
        return filled(config('letters.types.' . $letterType . '.template'));
    }

    /**
     * Whether a letter is templated — meaning it is shown, reviewed, and
     * exported entirely from fields() below, and content_html (a leftover
     * from the old free editor, or genuinely imported correspondence) is
     * never consulted for it, however it may be populated on the record.
     *
     * Before this, a letter that had ever been opened in the general
     * editor carried a frozen content_html snapshot, and the review and
     * show screens would silently prefer that snapshot over the live
     * template — so a letter could look different on screen from what it
     * actually exported as, with no visible reason why. A templated
     * letter now always renders from fields(), full stop; content_html
     * is read only for letters with no approved template at all.
     */
    public static function isTemplated(Document $letter): bool
    {
        return $letter->type === 'letter' && static::isExportable($letter->letter_type);
    }

    /**
     * The letter's live content, computed once, used everywhere a
     * templated letter is shown, reviewed, or exported.
     */
    public static function fields(Document $letter): array
    {
        $inspection = $letter->inspection;
        $entity     = $inspection?->entity;
        $cfg        = config('letters');

        $signature = $letter->signatures->firstWhere('stage', 'director')
                  ?? $letter->signatures->last();

        $fine = $letter->letter_type === 'fine'
            ? $letter->inspection?->fines()->latest()->first()
            : null;

        return [
            'ref'        => $letter->reference_number ?: str_repeat('.', 20),
            'year'       => ($letter->issued_at ?? $letter->created_at ?? now())->format('y'),
            'date'       => $letter->issued_at?->format('d/m/Y') ?: str_repeat('.', 14),

            'owner'      => $entity?->owner ?: '…………',
            'tel'        => $entity?->telephone ?: '…………',

            /* The salutation the drafting form actually offers — male,
               female, or company — mapped to its printed Kinyarwanda
               line. Previously the approved template had no marker for
               this at all: it always printed "Bwana;" regardless of what
               was chosen, because the field was never wired to the
               document. */
            'salutation' => data_get($cfg, 'salutation.' . ($letter->salutation ?? 'company'), 'Bwana/Madamu;'),

            'subject'    => $letter->subject ?: data_get($cfg, "types.{$letter->letter_type}.subject", ''),

            'insp_date'  => $inspection?->inspection_date?->format('d/m/Y') ?: '../.../202.',
            'premises'   => $entity?->name ?: '—',
            'sector'     => $entity?->sector ?: '……...',
            'upi'        => $entity?->upi ?: '—',
            'district'   => $entity?->district ?: '……...',

            'deadline'   => (string) ($letter->deadline_days
                ?: data_get($cfg, "types.{$letter->letter_type}.deadline", 30)),

            'signatory'  => $signature->signer_name
                ?? data_get($cfg, 'signatory.title', 'Chief Inspector'),

            'fine_amount'      => $fine ? number_format((float) $fine->amount, 0) : '',
            'fine_legal_basis' => $fine?->legal_basis ?: '',
            'fine_due_date'    => $fine?->due_date?->format('d/m/Y') ?: '',

            /* Always the inspector's own recommendations, written during
               the visit — never a list of failed checklist items, and
               never content_html. One letter, one source. */
            'recommendations' => static::recommendationLines($inspection),
        ];
    }

    private static function recommendationLines(?Inspection $inspection): array
    {
        $text = trim((string) $inspection?->recommendations);

        if ($text === '') {
            return ['Nta byifuzo byanditswe muri ubu bugenzuzi.'];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);

        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
    }

    private function fillAnnex(TemplateProcessor $t, array $lines): void
    {
        $escaped = array_map(
            fn ($l) => htmlspecialchars($l, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $lines
        );

        $t->setValue('recommendations', implode('</w:t><w:br/><w:t>', $escaped));
    }
}