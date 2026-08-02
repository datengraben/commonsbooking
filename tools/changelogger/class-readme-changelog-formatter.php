<?php
/**
 * Changelogger formatter that writes releases into the plugin's readme.txt.
 *
 * CommonsBooking keeps its user-facing changelog in the WordPress plugin
 * readme (`readme.txt`), using a format that differs from keepachangelog.com:
 *
 *     ## Changelog
 *
 *     ### 2.11.0 (02.08.2026)
 *     FIXED: Something was broken
 *     ENHANCED: Something got better
 *
 * This formatter teaches the Jetpack Changelogger `write` command to speak that
 * format. Existing entries are preserved verbatim -- only the new release is
 * rendered and prepended to the top of the `## Changelog` section, so no
 * historical entry (including any hand-written quirks) is ever rewritten.
 *
 * @package CommonsBooking
 */

namespace CommonsBooking\Changelogger;

use Automattic\Jetpack\Changelog\Changelog;
use Automattic\Jetpack\Changelog\ChangelogEntry;
use Automattic\Jetpack\Changelog\KeepAChangelogParser;
use Automattic\Jetpack\Changelogger\FormatterPlugin;
use Automattic\Jetpack\Changelogger\PluginTrait;

/**
 * Formatter for the `## Changelog` section of readme.txt.
 *
 * Extends the bare keepachangelog parser (which does not itself implement
 * FormatterPlugin) and mixes in PluginTrait, so that when the changelogger
 * loads this file it detects exactly one FormatterPlugin class -- this one.
 */
class ReadmeChangelogFormatter extends KeepAChangelogParser implements FormatterPlugin {

	use PluginTrait;

	/**
	 * The readme.txt heading that introduces the changelog section.
	 *
	 * @var string
	 */
	private const SECTION_HEADER = '## Changelog';

	/**
	 * Everything in the file up to (but not including) the first version entry.
	 *
	 * Captured during parse() and reproduced verbatim during format().
	 *
	 * @var string
	 */
	private $prologue = '';

	/**
	 * The existing version entries, verbatim (from the first `### ` to EOF).
	 *
	 * @var string
	 */
	private $entriesRaw = '';

	/**
	 * Object ids of the ChangelogEntry instances parsed from the existing file.
	 *
	 * Anything not in this set is a freshly added release that must be rendered.
	 *
	 * @var array<int,bool>
	 */
	private $existing = array();

	/**
	 * Parse readme.txt into a Changelog object.
	 *
	 * The surrounding readme content and the existing changelog entries are kept
	 * verbatim; the parsed ChangelogEntry objects exist only so the `write`
	 * command can determine the next version and deduplicate changes.
	 *
	 * @param string $changelog readme.txt contents.
	 * @return Changelog
	 */
	public function parse( $changelog ) {
		$changelog = strtr( (string) $changelog, array( "\r\n" => "\n", "\r" => "\n" ) );

		$this->prologue   = $changelog;
		$this->entriesRaw = '';
		$this->existing   = array();

		$ret = new Changelog();

		// Locate the "## Changelog" section header.
		if ( ! preg_match( '/^' . preg_quote( self::SECTION_HEADER, '/' ) . '[^\n]*$/m', $changelog, $m, PREG_OFFSET_CAPTURE ) ) {
			$ret->setPrologue( $changelog );
			return $ret;
		}
		$headerOffset = $m[0][1];

		// Find the first version entry after the section header.
		$entryOffset = strpos( $changelog, "\n### ", $headerOffset );
		if ( false === $entryOffset ) {
			$ret->setPrologue( $changelog );
			return $ret;
		}

		$this->prologue   = substr( $changelog, 0, $entryOffset );
		$this->entriesRaw = substr( $changelog, $entryOffset + 1 );

		// Build lightweight entries (version + date) from each `### ` block.
		$entries = array();
		foreach ( preg_split( '/\n(?=### )/', $this->entriesRaw ) as $block ) {
			if ( ! preg_match( '/^###\s+(\S+)/', $block, $mm ) ) {
				continue;
			}
			$data = array();
			if ( preg_match( '/^###\s+\S+\s*\(\s*(\d{1,2})\.(\d{1,2})\.(\d{4})/', $block, $dm ) ) {
				$data['timestamp'] = sprintf( '%04d-%02d-%02d', $dm[3], $dm[2], $dm[1] );
			}
			$entry = $this->newChangelogEntry( $mm[1], $data );
			$this->existing[ spl_object_id( $entry ) ] = true;
			$entries[] = $entry;
		}

		$ret->setPrologue( $this->prologue );
		$ret->setEntries( $entries );
		return $ret;
	}

	/**
	 * Render a Changelog object back into readme.txt contents.
	 *
	 * @param Changelog $changelog Changelog object.
	 * @return string
	 */
	public function format( Changelog $changelog ) {
		$new = '';
		foreach ( $changelog->getEntries() as $entry ) {
			if ( isset( $this->existing[ spl_object_id( $entry ) ] ) ) {
				continue;
			}
			$new .= $this->formatEntry( $entry );
		}

		$prologue = rtrim( $this->prologue, "\n" );

		if ( '' === $new ) {
			// Nothing new to add: reproduce the file unchanged.
			return '' === $this->entriesRaw
				? $prologue . "\n"
				: $prologue . "\n\n" . $this->entriesRaw;
		}

		$new = rtrim( $new, "\n" ) . "\n\n";

		if ( '' === $this->entriesRaw ) {
			return $prologue . "\n\n" . rtrim( $new, "\n" ) . "\n";
		}
		return $prologue . "\n\n" . $new . $this->entriesRaw;
	}

	/**
	 * Render a single release in the readme.txt changelog format.
	 *
	 * @param ChangelogEntry $entry Changelog entry.
	 * @return string
	 */
	private function formatEntry( ChangelogEntry $entry ) {
		$timestamp = $entry->getTimestamp();
		$date      = null !== $timestamp ? $timestamp->format( 'd.m.Y' ) : 'unreleased';

		$out = '### ' . $entry->getVersion() . " ($date)\n";
		foreach ( $entry->getChangesBySubheading() as $subheading => $changes ) {
			$prefix = '' !== $subheading ? strtoupper( $subheading ) . ': ' : '';
			foreach ( $changes as $change ) {
				$text = trim( $change->getContent() );
				if ( '' === $text ) {
					continue;
				}
				// readme.txt keeps one change per line.
				$text = preg_replace( '/\s*\n\s*/', ' ', $text );
				$out .= $prefix . $text . "\n";
			}
		}
		return $out;
	}
}
