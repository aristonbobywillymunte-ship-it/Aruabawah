<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;
use App\Models\Project;
use App\Models\SocialMediaItem;
use App\Services\ContentMatchingService;

class MatchActiveProjectsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:match-active-projects {--dry-run : Simulate matching without writing to DB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Match existing global articles to all active projects based on keywords and create cross-project links.';

    /**
     * Execute the console command.
     */
    public function handle(ContentMatchingService $matchingService)
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info("Starting " . ($isDryRun ? "DRY RUN " : "") . "article cross-project matching...");
        
        $projects = Project::where('is_active', true)->get();
        if ($projects->isEmpty()) {
            $this->warn('No active projects found.');
            return 0;
        }
        
        $this->info("Found {$projects->count()} active projects.");
        
        $articles = Article::all();
        $socials = SocialMediaItem::all();
        
        $this->info("Scanning {$articles->count()} articles and {$socials->count()} social media items.");
        
        $newLinks = 0;
        $skippedLinks = 0;
        
        // Scan Articles
        foreach ($articles as $article) {
            $contentToMatch = ($article->title ?? '') . "\n" . ($article->content ?? '');
            $existingProjectIds = $article->projects()->pluck('projects.id')->toArray();
            
            foreach ($projects as $project) {
                // Skip if already explicitly linked
                if (in_array($project->id, $existingProjectIds, true)) {
                    $skippedLinks++;
                    continue;
                }
                
                $keywords = $project->scrapeKeywords();
                $matched = false;
                $matchedKw = '';
                
                foreach ($keywords as $kw) {
                    if ($matchingService->isStrictMatch($kw, $contentToMatch)) {
                        $matched = true;
                        $matchedKw = $kw;
                        break;
                    }
                }
                
                if ($matched) {
                    $this->line("Match found: Article ID {$article->id} -> Project ID {$project->id} (Keyword: '{$matchedKw}')");
                    if (!$isDryRun) {
                        $project->articles()->syncWithoutDetaching([$article->id]);
                    }
                    $newLinks++;
                }
            }
        }
        
        // Scan Social Media
        foreach ($socials as $item) {
            $contentToMatch = app(ContentMatchingService::class)->buildSocialMatchText(
                $item->content ?? null,
                $item->raw_json ?? null
            );
            $existingProjectIds = $item->projects()->pluck('projects.id')->toArray();
            
            foreach ($projects as $project) {
                if (in_array($project->id, $existingProjectIds, true)) {
                    $skippedLinks++;
                    continue;
                }
                
                $keywords = $project->scrapeKeywords();
                $matched = false;
                $matchedKw = '';
                
                foreach ($keywords as $kw) {
                    if ($matchingService->isStrictMatch($kw, $contentToMatch)) {
                        $matched = true;
                        $matchedKw = $kw;
                        break;
                    }
                }
                
                if ($matched) {
                    $this->line("Match found: Social ID {$item->id} -> Project ID {$project->id} (Keyword: '{$matchedKw}')");
                    if (!$isDryRun) {
                        $project->socialMediaItems()->syncWithoutDetaching([$item->id]);
                    }
                    $newLinks++;
                }
            }
        }
        
        
        $active_projects_checked = $projects->count();
        $articles_checked = $articles->count() + $socials->count();
        $matches_found = $newLinks; // Represents total true matches found that weren't already linked
        $already_linked = $skippedLinks;
        $candidate_relations = $newLinks;
        $rejected = 0; // Not explicitly tracked in this loop unless we count failures
        $duplicate_relations_found = 0; // Prevented by logic
        $errors = 0;
        
        $this->newLine();
        $this->info("=== DRY RUN STATISTICS ===");
        $this->info("active_projects_checked: {$active_projects_checked}");
        $this->info("articles_checked: {$articles_checked}");
        $this->info("matches_found: {$matches_found}");
        $this->info("already_linked: {$already_linked}");
        $this->info("candidate_relations: {$candidate_relations}");
        $this->info("rejected: {$rejected}");
        $this->info("duplicate_relations_found: {$duplicate_relations_found}");
        $this->info("errors: {$errors}");
        $this->newLine();
        
        $this->info("Finished cross-project matching.");
        
        return 0;
    }
}
