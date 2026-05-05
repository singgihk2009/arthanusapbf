<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;

class InitializeDocumentVersioning extends Command
{
    protected $signature = 'documents:initialize-versioning';
    protected $description = 'Initialize versioning data for existing documents';

    public function handle(): int
    {
        $summary = ['initialized'=>0,'duplicates_found'=>0,'current_fixed'=>0,'skipped'=>0,'failed'=>0];

        Document::query()->orderBy('id')->chunkById(200, function ($docs) use (&$summary) {
            foreach ($docs as $doc) {
                try {
                    $doc->update([
                        'version_number' => $doc->version_number ?: 1,
                        'version_type' => $doc->version_type ?: 'original',
                        'parent_document_id' => $doc->parent_document_id,
                        'is_current' => is_null($doc->is_current) ? true : (bool) $doc->is_current,
                    ]);
                    $summary['initialized']++;
                } catch (\Throwable $e) { $summary['failed']++; }
            }
        });

        $groups = Document::query()->selectRaw('business_id, owner_type, owner_id, document_type_id, COUNT(*) c')->groupBy('business_id','owner_type','owner_id','document_type_id')->havingRaw('COUNT(*) > 1')->get();
        foreach ($groups as $g) {
            $summary['duplicates_found']++;
            $docs = Document::query()->where('business_id',$g->business_id)->where('owner_type',$g->owner_type)->where('owner_id',$g->owner_id)->where('document_type_id',$g->document_type_id)->orderByRaw("CASE WHEN status='verified' THEN 0 ELSE 1 END")->orderByDesc('created_at')->orderByDesc('id')->get();
            $keep = $docs->first();
            foreach ($docs as $doc) {
                $target = $doc->id === $keep->id;
                if ((bool)$doc->is_current !== $target) { $doc->update(['is_current'=>$target]); $summary['current_fixed']++; }
            }
        }

        $this->info(json_encode($summary));
        return self::SUCCESS;
    }
}
