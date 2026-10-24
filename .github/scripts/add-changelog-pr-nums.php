#!/usr/bin/env php
<?php
/**
 * Stamp each queued changelog entry with the pull request that introduced it.
 *
 * Replaces changelogger's own `--add-pr-num`, which resolves the wrong PR on
 * stable releases. It runs:
 *
 *     git log -1 --first-parent --format=%s <entry-file>
 *
 * and `--first-parent` is the problem. Stable tags are cut from `main`, where
 * the develop-to-main release PR lands as a merge commit, so the first-parent
 * walk stops there and every entry in the release gets stamped with the
 * release PR instead of the work that produced it. 0.36.0 shipped that way:
 * all four entries pointed at #100, the release merge.
 *
 * Dropping `--first-parent` walks into the merged branch and finds the commit
 * that actually added the file, whose squash-merge subject carries the real
 * number. Asking for the adding commit rather than the latest one also keeps
 * a later wording fix from taking the credit, which gave #93's entry to #97
 * in 0.36.0-beta.2.
 *
 * Runs before `changelogger write` (which must then be called *without*
 * `--add-pr-num`) and appends a ` [#123]` marker to each entry's message.
 * Ported from GatherPress core's script of the same name.
 *
 * Idempotent: an entry that already ends in a marker is left alone.
 *
 * @package GatherPress_Alpha
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
 */

$changelog_dir = dirname( __DIR__, 2 ) . '/.github/changelog';

if ( ! is_dir( $changelog_dir ) ) {
	echo "No .github/changelog directory; nothing to stamp.\n";
	exit( 0 );
}

/**
 * Resolve the pull request number that added a changelog entry file.
 *
 * Deliberately omits `--first-parent`; see the file docblock.
 *
 * @param string $entry_file Absolute path to the entry file.
 * @return string Pull request number, or '' when none can be determined.
 */
function gatherpress_alpha_pr_for_entry( $entry_file ) {
	$command = sprintf(
		'git log --diff-filter=A -1 --format=%%s -- %s 2>/dev/null',
		escapeshellarg( $entry_file )
	);

	$subject = trim( (string) shell_exec( $command ) );

	if ( '' === $subject ) {
		return '';
	}

	// Same shapes changelogger accepts: a GitHub merge commit, or a squash
	// merge whose subject ends in the PR number.
	$matches = array();

	if ( preg_match( '/^Merge pull request #(\d+)/', $subject, $matches ) ) {
		return $matches[1];
	}

	if ( preg_match( '/\(#(\d+)\)\s*$/', $subject, $matches ) ) {
		return $matches[1];
	}

	return '';
}

$stamped = 0;
$skipped = array();

foreach ( new DirectoryIterator( $changelog_dir ) as $file ) {
	if ( $file->isDot() || ! $file->isFile() || '.' === $file->getBasename()[0] ) {
		continue;
	}

	$entry_path = $file->getPathname();
	$contents   = file_get_contents( $entry_path );

	if ( false === $contents ) {
		fwrite( STDERR, "Unable to read {$entry_path}\n" );
		exit( 1 );
	}

	// Entry files are a header block, a blank line, then the message.
	$parts = preg_split( '/\R\R/', $contents, 2 );

	if ( 2 !== count( $parts ) ) {
		$skipped[] = $file->getBasename() . ' (no message body)';
		continue;
	}

	list( $header, $message ) = $parts;
	$message                  = rtrim( $message );

	if ( '' === $message ) {
		$skipped[] = $file->getBasename() . ' (empty message)';
		continue;
	}

	if ( preg_match( '/\[#\d+\](?:\([^)]*\))?$/', $message ) ) {
		continue;
	}

	$pr_number = gatherpress_alpha_pr_for_entry( $entry_path );

	if ( '' === $pr_number ) {
		$skipped[] = $file->getBasename() . ' (no PR found in the adding commit)';
		continue;
	}

	file_put_contents( $entry_path, $header . "\n\n" . $message . " [#{$pr_number}]\n" );
	++$stamped;
}

echo "Stamped {$stamped} changelog entr" . ( 1 === $stamped ? 'y' : 'ies' ) . " with a PR number.\n";

foreach ( $skipped as $note ) {
	echo "  skipped: {$note}\n";
}
