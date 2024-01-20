itinerisltd/wp-cli-post-meta-import
===================================



[![Build Status](https://travis-ci.org/itinerisltd/wp-cli-post-meta-import.svg?branch=master)](https://travis-ci.org/itinerisltd/wp-cli-post-meta-import)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

~~~
wp post meta import <file> [--[no-]dry-run] [--yes]
~~~

Processes a JSON array of objects with requried `"url"` property.
URLs will be passed to [url_to_postid()](https://developer.wordpress.org/reference/functions/url_to_postid/) to find a post ID.

Each object property will be treated as a meta field to update.
Meta updates must be provides in a `"key": "value"` pair.

Values will be whitespace trimmed and skipped if empty.

Does not support terms.

**OPTIONS**

	<file>
		The input JSON file to parse. Path must be relative to ABSPATH.

	[--[no-]dry-run]
		Whether to just report on changes or also save changes to database.
		--
		default: --dry-run
		--

	[--yes]
		Confirm running without prompt.

**SAMPLE JSON**

    [
      {
        "url": "https://example.com/sample-page",
        "my_meta_field": "My new value!",
        "_yoast_wpseo_title": "My new SEO title",
        "_yoast_wpseo_metadesc": "My new SEO description",
        "_yoast_wpseo_canonical": "https://example.co.uk/sample-page"
      },
      {
        "url": "https://example.com/hello-world",
        "my_meta_field": "My new value!",
        "_yoast_wpseo_title": "My new SEO title",
        "_yoast_wpseo_metadesc": "", # This will not be changed
        "_yoast_wpseo_canonical": "https://example.co.uk/foo-bar"
      }
    ]

**EXAMPLES**

    $ wp post meta import wp-content/uploads/post-meta.json --dry-run
    350 detected records to process
    Are you ready to process 350 records? [y/n]
    ...
    Finished: Rows processed: 350. Meta processed: 981.
    ---
    $ wp post meta import wp-content/uploads/post-meta.json --yes --no-dry-run
    ...
    Finished. Rows processed: 350. Meta processed: 981. Meta updated: 440. Meta skipped: 535. Meta unchanged: 6. Meta failed 0.

## Installing

Installing this package requires WP-CLI v2.5 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install itinerisltd/wp-cli-post-meta-import:@stable
```

To install the latest development version of this package, use the following command instead:

```bash
wp package install itinerisltd/wp-cli-post-meta-import:dev-master
```

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/itinerisltd/wp-cli-post-meta-import/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/itinerisltd/wp-cli-post-meta-import/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/itinerisltd/wp-cli-post-meta-import/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

GitHub issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
