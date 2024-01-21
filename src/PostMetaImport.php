<?php

declare(strict_types=1);

namespace ItinerisLtd\PostMetaImport;

use Exception;
use League\Csv\Reader;
use League\Csv\UnavailableStream;
use WP_CLI;
use WP_CLI_Command;

class PostMetaImport extends WP_CLI_Command
{
    protected array $data;
    protected int $row_count;
    protected int $rows_processed = 0;
    protected int $rows_failed = 0;
    protected int $meta_processed = 0;
    protected int $meta_updated = 0;
    protected int $meta_failed = 0;
    protected int $meta_skipped = 0;
    protected int $meta_unchanged = 0;

    /**
     * Bulk import meta data for posts.
     *
     * Imports post meta for URLs from CSV or JSON files.
     * URLs will be passed to [url_to_postid()](https://developer.wordpress.org/reference/functions/url_to_postid/) to find a post ID.
     *
     * If providing a CSV file, the first row will be used as a header row for meta keys.
     * If providing a JSON file, each object key:value pair will be used for meta_key:meta_value.
     *
     * Keys and values will be whitespace trimmed and skipped if empty.
     *
     * Does not support terms.
     *
     * ## OPTIONS
     *
     * <file>
     * : The input file to parse. Path must be relative to ABSPATH.
     *
     * [--[no-]dry-run]
     * : Whether to just report on changes or also save changes to database.
     * --
     * default: --dry-run
     * --
     *
     * [--yes]
     * : Confirm running without prompt.
     *
     * ## SAMPLE JSON
     *
     *     [
     *       {
     *         "url": "https://example.com/sample-page",
     *         "my_meta_field": "My new value!",
     *         "_yoast_wpseo_title": "My new SEO title",
     *         "_yoast_wpseo_metadesc": "My new SEO description",
     *         "_yoast_wpseo_canonical": "https://example.co.uk/sample-page"
     *       },
     *       {
     *         "url": "https://example.com/hello-world",
     *         "my_meta_field": "My new value!",
     *         "_yoast_wpseo_title": "My new SEO title",
     *         "_yoast_wpseo_metadesc": "",
     *         "_yoast_wpseo_canonical": "https://example.co.uk/foo-bar"
     *       }
     *     ]
     *
     * ## SAMPLE CSV
     *     url,my_meta_field,_yoast_wpseo_title,_yoast_wpseo_metadesc,_yoast_wpseo_canonical
     *     https://example.com/sample-page,My new value!,My new SEO title,My new SEO description,https://example.co.uk/sample-page
     *     https://example.com/hello-world,My new value!,My new SEO title,,https://example.co.uk/foo-bar
     *
     * ## EXAMPLES
     *
     *     $ wp post meta import wp-content/uploads/post-meta.csv --dry-run
     *     350 detected records to process
     *     Are you ready to process 350 records? [y/n]
     *     ...
     *     Finished.
     *     Rows processed: 350. Meta processed: 981.
     *     ---
     *     $ wp post meta import wp-content/uploads/post-meta.json --yes --no-dry-run
     *     350 detected records to process
     *     ...
     *     Finished.
     *     Rows processed: 350. Meta processed: 981. Meta updated: 440. Meta skipped: 535. Meta unchanged: 6. Meta failed 0.
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $input_file = $args[0] ?? null;
        if (empty($input_file)) {
            WP_CLI::error('No input file provided');
        }

        if (! str_ends_with($input_file, '.csv') && ! str_ends_with($input_file, '.json')) {
            WP_CLI::error('Input file must be .csv or .json');
        }

        $this->data = $this->toArray($input_file);
        if (empty($this->data)) {
            WP_CLI::error('Input file is empty');
        }

        $this->row_count = count($this->data);
        WP_CLI::log("{$this->row_count} detected records to process");

        $confirm = $assoc_args['yes'] ?? false;
        if (false === $confirm) {
            WP_CLI::confirm("Are you ready to process {$this->row_count} records?");
        }

        $dry_run = $assoc_args['dry-run'] ?? true;
        if ($dry_run) {
            WP_CLI::warning('Executing as dry run');
        } else {
            WP_CLI::warning('Executing WITHOUT dry run.');
        }

        WP_CLI::log("Processing {$this->row_count} records...");
        $this->run($dry_run);

        WP_CLI::log(WP_CLI::colorize('%GFinished.%n'));
        if ($dry_run) {
            $message = sprintf('Rows processed: %d. Meta processed: %d.', $this->rows_processed, $this->meta_processed);
        } else {
            $message = sprintf(
                'Rows processed: %d. Rows failed: %d. Meta processed: %d. Meta updated: %d. Meta skipped: %d. Meta unchanged: %d. Meta failed %d.',
                $this->rows_processed,
                $this->rows_failed,
                $this->meta_processed,
                $this->meta_updated,
                $this->meta_skipped,
                $this->meta_unchanged,
                $this->meta_failed,
            );
        }

        WP_CLI::log($message);
        if ($this->meta_updated > 400) {
            WP_CLI::log('Please allow some time for post-execution cleanup.');
        }
    }

    /**
     * Process the records
     */
    protected function run(bool $dry_run = true): void
    {
        foreach ($this->data as $key => $data) {
            $this->rows_processed++;
            if (empty($data) || empty($data['url'])) {
                $this->rows_failed++;
                WP_CLI::error("Record or URL is empty for item with the index: {$key}", false);
                continue;
            }

            $post_id = url_to_postid($data['url']);
            if (empty($post_id)) {
                $this->rows_failed++;
                WP_CLI::error(
                    "Could not find post ID for {$data['url']}; the URL either doesn't exist or is not a post.",
                    false,
                );
                continue;
            }

            WP_CLI::log("Post #{$post_id} - {$data['url']}");
            $this->updatePost($post_id, $data, $dry_run);
        }
    }

    /**
     * Update a posts meta data.
     */
    protected function updatePost(int $post_id, array $fields, bool $dry_run = true): void
    {
        if (empty($post_id) || empty($fields)) {
            return;
        }

        $url = $fields['url'];
        unset($fields['url']);

        foreach ($fields as $key => $value) {
            $this->meta_processed++;
            $key = trim($key ?? '');
            $new_value = trim($value ?? '');
            $current_value = get_post_meta($post_id, $key, true);
            if ($dry_run) {
                if (empty($new_value)) {
                    continue;
                }

                WP_CLI::log(WP_CLI::colorize("%Y{$key} %CBefore:%n "));
                WP_CLI::log($current_value);
                WP_CLI::log(WP_CLI::colorize("%Y{$key} %CAfter:%n "));
                WP_CLI::log($new_value);
                continue;
            }

            if (empty($new_value)) {
                WP_CLI::warning("The value for field '{$key}' on '{$url}' is empty");
                $this->meta_skipped++;
                continue;
            }

            $update_meta = update_post_meta($post_id, $key, $new_value);
            if (false === $update_meta) {
                if ($current_value === $new_value) {
                    $this->meta_unchanged++;
                    WP_CLI::success("Value passed for field '{$key}' is unchanged on {$url}.");
                } else {
                    $this->meta_failed++;
                    WP_CLI::error("Failed to update value for '{$key}' on {$url}.", false);
                }
            } else {
                $this->meta_updated++;
                WP_CLI::success("Updated '{$key}' field on {$url}.");
            }
        }
    }

    protected function jsonToArray(string $file_path): array
    {
        $file_contents = file_get_contents($file_path);
        if (empty($file_contents)) {
            WP_CLI::error('Could not read input file.');
        }

        $records = json_decode($file_contents, true);
        if (empty($records)) {
            WP_CLI::error('Could not run json_decode on input file.');
        }

        return $records;
    }

    protected function csvToArray(string $file_path): array
    {
        try {
            $reader = Reader::createFromPath($file_path, 'r');
        } catch (UnavailableStream $err) {
            WP_CLI::error($err->getMessage(), true);
        }

        $reader->includeEmptyRecords();
        $reader->setHeaderOffset(0);

        try {
            $records = $reader->getRecords();
        } catch (Exception $err) {
            WP_CLI::error($err->getMessage(), true);
        }

        return iterator_to_array($records);
    }

    protected function toArray(string $input_file): array
    {
        $file_path = ABSPATH . ltrim($input_file, '/\\');
        if (false === file_exists($file_path)) {
            WP_CLI::error('Input file does not exist.');
            return [];
        }

        if (str_ends_with($input_file, '.json')) {
            return $this->jsonToArray($file_path);
        }

        return $this->csvToArray($file_path);
    }
}
