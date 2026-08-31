<?php

add_action('wp_head', 'inject_expert_person_schema');

function inject_expert_person_schema()
{
	if (!is_singular('expert')) return;

	global $post;

	// Checking transient cache
	$cache_key = 'expert_schema_' . $post->ID;
	$cached = get_transient($cache_key);
	if ($cached) {
		echo $cached;
		return;
	}

    // ... [Data fetching for bio, credentials, licenses, rates, and contact info] ...

    // Building the Person Schema object
	$person = [
		'@type' => 'Person',
		'@id' => $permalink . '#person',
		'name' => $title,
		'jobTitle' => $designation,
		'url' => $permalink,
		'image' => $featured_image,
		'sameAs' => $sameAs,
		'description' => wp_strip_all_tags(wp_trim_words(get_field('bio', $post->ID), 55, '…')),
		'mainEntityOfPage' => ['@id' => $permalink],
	];

    // ... [Building $credentials, $alumni, and service $offers] ...

	$schema['@graph'][] = $person;

	// Outputting the JSON-LD schema script directly into <head>
	$output = '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
	set_transient($cache_key, $output, 0);
	echo $output;
}
