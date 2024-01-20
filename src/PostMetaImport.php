<?php

declare(strict_types=1);

namespace ItinerisLtd\PostMetaImport;

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
     * Processes a JSON array of objects with requried `"url"` property.
     * URLs will be passed to [url_to_postid()](https://developer.wordpress.org/reference/functions/url_to_postid/) to find a post ID.
     *
     * Each object property will be treated as a meta field to update.
     * Meta updates must be provides in a `"key": "value"` pair.
     *
     * Values will be whitespace trimmed and skipped if empty.
     *
     * Does not support terms.
     *
     * ## OPTIONS
     *
     * <file>
     * : The input JSON file to parse. Path must be relative to ABSPATH.
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
     *         "_yoast_wpseo_metadesc": "", # This will not be changed
     *         "_yoast_wpseo_canonical": "https://example.co.uk/foo-bar"
     *       }
     *     ]
     *
     * ## EXAMPLES
     *
     *     $ wp post meta import wp-content/uploads/post-meta.json --dry-run
     *     350 detected records to process
     *     Are you ready to process 350 records? [y/n]
     *     ...
     *     Finished: Rows processed: 350. Meta processed: 981.
     *     ---
     *     $ wp post meta import wp-content/uploads/post-meta.json --yes --no-dry-run
     *     ...
     *     Finished. Rows processed: 350. Meta processed: 981. Meta updated: 440. Meta skipped: 535. Meta unchanged: 6. Meta failed 0.
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $input_file = $args[0] ?? null;
        if (empty($input_file)) {
            WP_CLI::error('No input file provided');
        }

        $this->data = $this->toArray($input_file);
        if (empty($this->data)) {
            WP_CLI::error('Parsed JSON data is empty');
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
            if (empty($data) || empty($data->url)) {
                $this->rows_failed++;
                WP_CLI::error("Data or data->url empty for item with the index: {$key} in json data", false);
                continue;
            }

            $post_id = url_to_postid($data->url);
            if (empty($post_id)) {
                $this->rows_failed++;
                WP_CLI::error(
                    "Could not find post ID for {$data->url}; the URL either doesn't exist or is not a post.",
                    false,
                );
                continue;
            }

            WP_CLI::log("Post #{$post_id} - {$data->url}");
            $this->updatePost($post_id, $data, $dry_run);
            WP_CLI::log(PHP_EOL);
        }
    }

    /**
     * Update a posts meta data.
     */
    protected function updatePost(int $post_id, object $data, bool $dry_run = false): void
    {
        if (empty($post_id) || empty($data)) {
            return;
        }

        $fields = get_object_vars($data);
        unset($fields['url']);

        foreach ($fields as $key => $value) {
            $this->meta_processed++;
            $current_value = get_post_meta($post_id, $key, true);
            $new_value = trim($value ?? '');
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
                WP_CLI::warning("The value for field '{$key}' on '{$data->url}' is empty");
                $this->meta_skipped++;
                continue;
            }

            $update_meta = update_post_meta($post_id, $key, $new_value);
            if (false === $update_meta) {
                if ($current_value === $new_value) {
                    $this->meta_unchanged++;
                    WP_CLI::success("Value passed for field '{$key}' is unchanged on {$data->url}.");
                } else {
                    $this->meta_failed++;
                    WP_CLI::error("Failed to update value for '{$key}' on {$data->url}.", false);
                }
            } else {
                $this->meta_updated++;
                WP_CLI::success("Updated '{$key}' field on {$data->url}.");
            }
        }
    }

    protected function toArray(string $input_file): array
    {
        $file_path = ABSPATH . ltrim($input_file, '/\\');
        if (false === file_exists($file_path)) {
            WP_CLI::error('Input file does not exist.');
            return [];
        }

        $file_contents = file_get_contents($file_path);
        if (empty($file_contents)) {
            WP_CLI::error('Could not read input file.');
        }

        $json = json_decode($file_contents);
        if (empty($json)) {
            WP_CLI::error('Could not run json_decode on input file.');
        }

        return $json;
    }
}
