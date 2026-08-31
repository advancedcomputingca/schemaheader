<?php

// Hook the custom schema generator function into WordPress's 'wp_head' action 
// so the JSON-LD script outputs inside the site's <head> tags.
add_action('wp_head', 'inject_expert_person_schema');

/**
 * Generates and injects structured JSON-LD Schema.org data into the document head
 * for single custom post type entries of 'expert'.
 */
function inject_expert_person_schema()
{
	// Early exit guard clause: ensure this runs ONLY on individual 'expert' Custom Post Type (CPT) pages.
	if (!is_singular('expert')) return;

	// Access the global WordPress $post object to retrieve the current post ID and details.
	global $post;

	// Generate a unique transient key based on the current post ID for caching.
	$cache_key = 'expert_schema_' . $post->ID;
	
	// Fetch cached schema markup from the WordPress transient API if it exists.
	$cached = get_transient($cache_key);
	if ($cached) {
		// Output cached markup directly and terminate function execution to reduce DB query load.
		echo $cached;
		return;
	}

    // ... [Data fetching for bio, credentials, licenses, rates, and contact info] ...

    // Construct the baseline Schema.org 'Person' entity array using retrieved metadata.
	$person = [
		'@type' => 'Person',                                                          // Defines schema entity as Schema.org/Person.
		'@id' => $permalink . '#person',                                              // Unique canonical URI identifier for this Person entity.
		'name' => $title,                                                             // Full name of the expert.
		'jobTitle' => $designation,                                                   // Professional title/designation (e.g., Therapist, Coach).
		'url' => $permalink,                                                          // Canonical profile page URL.
		'image' => $featured_image,                                                   // URL of the primary featured image profile photo.
		'sameAs' => $sameAs,                                                          // Array of external authoritative profiles (LinkedIn, website, socials).
		'description' => wp_strip_all_tags(wp_trim_words(get_field('bio', $post->ID), 55, '…')), // Truncated bio (first 55 words) with HTML stripped.
		'mainEntityOfPage' => ['@id' => $permalink],                                 // Binds this Person as the primary topic of the current web page.
	];

    // ... [Building $credentials, $alumni, and service $offers] ...

	// Append the fully constructed $person node into the top-level '@graph' array of the main $schema structure.
	$schema['@graph'][] = $person;

	// Encode the PHP array into clean JSON, ensuring forward slashes are unescaped and UTF-8 characters are preserved, wrapped in standard script tags.
	$output = '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
	
	// Store the final HTML string in transients permanently (expiration = 0) until manually invalidated (e.g., via save_post action).
	set_transient($cache_key, $output, 0);
	
	// Print the JSON-LD schema string directly into the head element of the page.
	echo $output;
}
