<?php
/*
Plugin Name: Expert Schema
Description: Injects custom Person schema for expert CPT using wp_head with caching
*/



// Dynamically load terms from multiple taxonomies
add_filter('acf/load_field/name=primary_designation', function ($field) {
	$taxonomies = ['addictions-specialist', 'coach', 'counselor', 'social-worker', 'therapist']; // control order here

	$choices = [];

	foreach ($taxonomies as $taxonomy) {
		$terms = get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);

		if (! is_wp_error($terms) && ! empty($terms)) {
			foreach ($terms as $term) {
				$label = get_taxonomy($taxonomy)->labels->singular_name;
				$choices[$term->term_id] = $label . ': ' . $term->name;
			}
		}
	}

	$field['choices'] = $choices;

	return $field;
});

// Shortcode to display link to primary designation
add_shortcode('primary_designation_link', function () {
	$post_id = get_the_ID();
	if (!$post_id) return '';

	$term_id = get_field('primary_designation', $post_id);
	if (!$term_id) return '';

	$term = get_term($term_id);
	if (!$term || is_wp_error($term)) return '';

	$link = get_term_link($term);
	if (is_wp_error($link)) return '';

	return sprintf('<a href="%s">%s</a>', esc_url($link), esc_html($term->name));
});

// Shortcode to display license info
add_shortcode('expert_license_info', function () {
	if (!is_singular('expert')) return '';

	$rows = get_field('designation_license');
	if (!$rows || !is_array($rows)) return '';

	// Map designation slugs to subfield keys
	$designation_to_field = [
		'therapist'             => 'Therapist',
		'coach'                 => 'Coach',
		'counselor'             => 'counselor',
		'social-worker'         => 'Social_Worker',
		'addictions-specialist' => 'Addictions_Specialist',
	];

	$output = '';

	foreach ($rows as $row) {
		// Step 1: Get designation term
		$designation_term_id = $row['designation'] ?? null;
		if (!$designation_term_id || !is_numeric($designation_term_id)) continue;

		$designation_term = get_term((int)$designation_term_id);
		if (!$designation_term || is_wp_error($designation_term)) continue;

		$slug = $designation_term->slug;
		$field_key = $designation_to_field[$slug] ?? null;
		if (!$field_key) continue;

		// Step 2: Get selected license term from mapped subfield
		$license_term_id = $row[$field_key] ?? null;
		if (!$license_term_id || !is_numeric($license_term_id)) continue;

		$license_term = get_term((int)$license_term_id);
		if (!$license_term || is_wp_error($license_term)) continue;

		// Step 3: Build output
		$parts = [ esc_html($license_term->name) ];

		if (!empty($row['licensing_body'])) {
			$parts[] = esc_html($row['licensing_body']);
		}

		if (!empty($row['licence_number'])) {
			$parts[] = 'License: ' . esc_html($row['licence_number']);
		}

		$output .= '<div style="padding:8px 0;border-bottom:1px solid #EAEAEA;">' . implode(', ', $parts) . '</div>';
	}

	return $output;
});

// Shortcode to display top 3 specializations
add_shortcode('expert_top_3_specializations', function () {
	global $post;
	if (!$post || $post->post_type !== 'expert') return '';

	$taxonomies = ['clinical-areas-top-3', 'coaching-focus-areas-top-3'];
	$terms = [];

	foreach ($taxonomies as $taxonomy) {
		$terms = get_the_terms($post->ID, $taxonomy);
		if (!empty($terms) && !is_wp_error($terms)) {
			$terms = array_slice($terms, 0, 3);
			break;
		}
	}

	if (empty($terms)) return '';

	$output = '<div class="specializationsli"><span class="elementor-heading-title elementor-size-default"><ul>';

	foreach ($terms as $term) {
		$url = get_term_link($term);
		if (is_wp_error($url)) continue;

		$output .= '<li style="margin-bottom: 6px;"><a href="' . esc_url($url) . '" rel="tag">' . esc_html($term->name) . '</a></li>';
	}

	$output .= '</ul></span></div>';

	return $output;
});

//filter to show guest author
add_action('wp_head', function () {
	if (! is_singular('post')) return;

	$post_id       = get_the_ID();
	$transient_key = 'offline_schema_' . $post_id;
	$schema        = get_transient($transient_key);

	if ($schema === false) {
		$guest_author = trim(get_field('guest_author', $post_id));
		$expert_url   = esc_url(get_field('expert_profile_link', $post_id));

		$wp_author_id = get_post_field('post_author', $post_id);
		$wp_author    = get_userdata($wp_author_id);
		$wp_author_url = get_author_posts_url($wp_author_id);
		$wp_author_name = $wp_author->display_name;
		$wp_author_avatar = get_avatar_url($wp_author_id);

		$headline   = get_the_title($post_id);
		$desc       = get_the_excerpt($post_id);
		$published  = get_the_date(DATE_W3C, $post_id);
		$modified   = get_the_modified_date(DATE_W3C, $post_id);
		$image_id   = get_post_thumbnail_id($post_id);
		$image_src  = wp_get_attachment_image_src($image_id, 'full');
		$image_url  = $image_src ? $image_src[0] : '';
		$image_width  = $image_src ? $image_src[1] : '';
		$image_height = $image_src ? $image_src[2] : '';
		$image_caption = get_post(get_post_thumbnail_id())->post_excerpt;

		$site_url = get_site_url();
		$site_name = get_bloginfo('name');
		$post_url = get_permalink($post_id);

		$author_block = [
			'@type' => 'Person',
			'name'  => $guest_author ?: $wp_author_name,
			'@id'   => $guest_author && $expert_url ? $expert_url : $wp_author_url,
			'url'   => $guest_author && $expert_url ? $expert_url : $wp_author_url,
			'worksFor' => [
				'@id' => $site_url . '/#organization'
			],
		];

		if (! $guest_author && $wp_author_avatar) {
			$author_block['image'] = [
				'@type' => 'ImageObject',
				'@id' => $wp_author_avatar,
				'url' => $wp_author_avatar,
				'caption' => $wp_author_name,
				'inLanguage' => 'en-US'
			];
		}

		$schema_array = [
			"@context" => "https://schema.org",
			"@graph"   => [
				[
					"@type" => "Organization",
					"@id"   => $site_url . "/#organization",
					"name"  => $site_name,
					"url"   => $site_url,
					"logo"  => [
						"@type" => "ImageObject",
						"@id"   => $site_url . "/#logo",
						"url"   => $site_url . "/wp-content/uploads/2025/04/logo.svg",
						"contentUrl" => $site_url . "/wp-content/uploads/2025/04/logo.svg",
						"caption"    => $site_name,
						"inLanguage" => "en-US",
					],
				],
				[
					"@type" => "WebSite",
					"@id"   => $site_url . "/#website",
					"url"   => $site_url,
					"name"  => $site_name,
					"publisher" => [
						"@id" => $site_url . "/#organization"
					],
					"inLanguage" => "en-US",
				],
				[
					"@type" => "ImageObject",
					"@id"   => $image_url,
					"url"   => $image_url,
					"width" => $image_width,
					"height" => $image_height,
					"caption" => $image_caption,
					"inLanguage" => "en-US",
				],
				[
					"@type" => "WebPage",
					"@id"   => $post_url . "#webpage",
					"url"   => $post_url,
					"name"  => $headline,
					"datePublished" => $published,
					"dateModified"  => $modified,
					"isPartOf" => [
						"@id" => $site_url . "/#website"
					],
					"primaryImageOfPage" => [
						"@id" => $image_url
					],
					"inLanguage" => "en-US",
				],
				[
					"@type" => "Article",
					"@id" => $post_url . "#richSnippet",
					"headline" => $headline,
					"name"     => $headline,
					"datePublished" => $published,
					"dateModified"  => $modified,
					"description"   => $desc,
					"articleSection" => get_the_category($post_id)[0]->name ?? '',
					"author"  => $author_block,
					"publisher" => [
						"@id" => $site_url . "/#organization"
					],
					"image" => [
						"@id" => $image_url
					],
					"isPartOf" => [
						"@id" => $post_url . "#webpage"
					],
					"mainEntityOfPage" => [
						"@id" => $post_url . "#webpage"
					],
					"inLanguage" => "en-US",
				],
			]
		];

		$schema = '<script type="application/ld+json" class="rank-math-schema-pro">' . wp_json_encode($schema_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
		set_transient($transient_key, $schema, DAY_IN_SECONDS);
	}

	echo $schema;
}, 99);

add_action('save_post', function ($post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	delete_transient('offline_schema_' . $post_id);
});

add_action('wp_head', 'inject_expert_person_schema');

function inject_expert_person_schema()
{
	if (!is_singular('expert')) return;

	global $post;

	$cache_key = 'expert_schema_' . $post->ID;
	$cached = get_transient($cache_key);
	if ($cached) {
		echo $cached;
		return;
	}

	$permalink      = get_permalink($post);
	$title          = get_the_title($post);
	$featured_image = get_the_post_thumbnail_url($post->ID, 'full');
	$locations      = get_field('locations', $post->ID);
	$date_published = get_the_date(DATE_W3C, $post->ID);
	$date_modified  = get_the_modified_date(DATE_W3C, $post->ID);

	$primary_designation_term_id = get_field('primary_designation', $post->ID);
	$designation = '';

	if ($primary_designation_term_id) {
		$term = get_term($primary_designation_term_id);
		if ($term && !is_wp_error($term)) {
			$taxonomy_slug = $term->taxonomy;

			// Human-readable job title based on taxonomy slug
			$taxonomy_labels = [
				'therapist' => 'Therapist',
				'counselor' => 'Counselor',
				'social-worker' => 'Social Worker',
				'coach' => 'Coach',
				'addictions-specialist' => 'Addictions Specialist',
			];

			$designation = $taxonomy_labels[$taxonomy_slug] ?? '';
		}
	}

	$sameAs_raw = array_filter([
		get_field('linkedin_url', $post->ID),
		get_field('google_business_url', $post->ID),
		get_field('instagram_url', $post->ID),
		get_field('facebook_url', $post->ID),
		get_field('tiktok_url', $post->ID),
		get_field('youtube_url', $post->ID),
		get_field('bluesky_url', $post->ID),
		get_field('website_url', $post->ID),
	]);
	$sameAs_raw[] = $permalink;

	$sameAs = array_values(array_filter($sameAs_raw, function ($url) {
		return filter_var($url, FILTER_VALIDATE_URL);
	}));

	$knows_about = [];

	$top_3s = get_field('top_3s', $post->ID);

	foreach (['clinical_areas-t3', 'coaching-focus-areas-top-3'] as $field_name) {
		$terms = $top_3s[$field_name] ?? [];

		if (!empty($terms) && is_array($terms)) {
			foreach ($terms as $term_id) {
				$term = get_term($term_id);
				if ($term && !is_wp_error($term)) {
					$knows_about[] = $term->name;
				}
			}
		}
	}

	$min_price = get_field('lowest_rate', $post->ID);
	$max_price = get_field('highest_rate', $post->ID);
	$currency_code = get_field('currency', $post->ID) ?: 'CAD';
	$modalities = get_field('top_3s', $post->ID)['modalities_&_techniques-t3'] ?? [];
	$booking_url = get_field('booking_url', $post->ID) ?: get_field('website_url', $post->ID);

	$email     = trim(get_field('contact_email', $post->ID));
	$phone     = trim(get_field('primary_phone', $post->ID));
	$languages = [];

	$language_term_ids = get_field('languages', $post->ID);

	if (!empty($language_term_ids) && is_array($language_term_ids)) {
		foreach ($language_term_ids as $term_id) {
			$term = get_term($term_id);
			if ($term && !is_wp_error($term)) {
				$languages[] = $term->name;
			}
		}
	}

	$region_ids = (array) get_field('Licensed_Regions', $post->ID);
	$regions = [];

	foreach ($region_ids as $id) {
		if ($id == 369) {
			$regions[] = 'United States';
		} elseif ($id == 370) {
			$regions[] = 'Canada';
		} else {
			$term = get_term($id);
			if ($term && !is_wp_error($term)) {
				$regions[] = $term->name;
			}
		}
	}
	$webpage_description = implode(', ', array_filter([$title, $designation, $locations]));

	$schema = [
		'@context' => 'https://schema.org',
		'@graph' => [
			[
				'@type' => 'WebPage',
				'@id' => $permalink,
				'url' => $permalink,
				'name' => "$title - " . get_bloginfo('name'),
				'primaryImageOfPage' => [
					'@id' => $permalink . '#primaryimage',
				],
				'description' => $webpage_description,
				'datePublished' => $date_published,
				'dateModified' => $date_modified,
				'inLanguage' => get_bloginfo('language') ?: 'en-US',
				'potentialAction' => [[
					'@type' => 'ReadAction',
					'target' => [$permalink]
				]]
			],
			[
				'@type' => 'BreadcrumbList',
				'@id' => $permalink . '#breadcrumb',
				'itemListElement' => [
					[
						'@type' => 'ListItem',
						'position' => 1,
						'name' => 'Home',
						'item' => home_url('/'),
					],
					[
						'@type' => 'ListItem',
						'position' => 2,
						'name' => 'Expert Directory',
						'item' => home_url('/expert/'),
					],
					[
						'@type' => 'ListItem',
						'position' => 3,
						'name' => $title,
						'item' => $permalink,
					],
				]
			]
		]
	];

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

	if (!empty($email)) {
		$person['email'] = 'mailto:' . $email;
	}

	if (!empty($phone)) {
		$person['telephone'] = $phone;
	}

	if ($email || $phone || $locations || !empty($languages)) {
		$contact_point = [
			'@type' => 'ContactPoint',
			'contactType' => 'Customer Service',
		];

		if (!empty($phone)) $contact_point['telephone'] = $phone;
		if (!empty($email)) $contact_point['email'] = 'mailto:' . $email;
		if (!empty($locations)) $contact_point['areaServed'] = $locations;
		if (!empty($languages)) $contact_point['availableLanguage'] = $languages;

		$person['contactPoint'] = $contact_point;
	}

	if (!empty($knows_about)) {
		$person['knowsAbout'] = $knows_about;
	}

	$credentials = [];
	$alumni = [];


$rows = get_field('designation_license');
if ($rows && is_array($rows)) {
	// Map designation slugs to their subfield names
	$designation_to_field = [
		'therapist'             => 'Therapist',
		'coach'                 => 'Coach',
		'counselor'             => 'counselor',
		'social-worker'         => 'Social_Worker',
		'addictions-specialist' => 'Addictions_Specialist',
	];

	foreach ($rows as $row) {
		$license_body   = trim($row['licensing_body'] ?? '');
		$license_number = trim($row['licence_number'] ?? '');
		$term_name      = null;

		// Get the selected designation term
		$designation_id = $row['designation'] ?? null;
		if (!$designation_id || !is_numeric($designation_id)) continue;

		$designation_term = get_term((int)$designation_id);
		if (!$designation_term || is_wp_error($designation_term)) continue;

		// Use the slug to get the correct field key
		$slug = $designation_term->slug;
		$field_key = $designation_to_field[$slug] ?? null;
		if (!$field_key) continue;

		// Get the selected license term from that field
		$term_id = $row[$field_key] ?? null;
		if ($term_id && is_numeric($term_id)) {
			$term_obj = get_term((int)$term_id);
			if ($term_obj && !is_wp_error($term_obj)) {
				$term_name = $term_obj->name;
			}
		}

		if ($license_body && $term_name) {
			$license = [
				'@type' => 'EducationalOccupationalCredential',
				'name' => $term_name,
				'credentialCategory' => 'License',
				'educationalLevel' => 'Professional degree',
				'recognizedBy' => [
					'@type' => 'Organization',
					'name' => $license_body,
				],
			];

			if (!empty($license_number)) {
				$license['identifier'] = [
					'@type' => 'PropertyValue',
					'name' => 'License Number',
					'value' => $license_number,
				];
			}

			if (!empty($row['licensing_body_url']) && filter_var($row['licensing_body_url'], FILTER_VALIDATE_URL)) {
				$license['recognizedBy']['url'] = $row['licensing_body_url'];
			}

			if (!empty($row['licensing_body_individual_profile']) && filter_var($row['licensing_body_individual_profile'], FILTER_VALIDATE_URL)) {
				$license['url'] = $row['licensing_body_individual_profile'];
			}

			$credentials[] = $license;
		}
	}
}
	$degrees = get_field('degrees', $post->ID);
	if (!empty($degrees) && is_array($degrees)) {
		$level_map = [
			'associate' => 'Associate degree',
			'bachelor' => 'Bachelor degree',
			'master' => 'Master degree',
			'doctoral' => 'Doctoral degree',
			'professional' => 'Professional degree',
			'postgraduate_certificate' => 'Postgraduate certificate',
			'certificate' => 'Certificate',
			'diploma' => 'Diploma',
			'other' => 'Other',
		];

		foreach (array_slice($degrees, 0, 5) as $row) {
			$degree = trim($row['degree'] ?? '');
			$level = trim($row['educational_level'] ?? '');
			$school = trim($row['university'] ?? '');
			$city = trim($row['city'] ?? '');
			$year = trim($row['year_graduated'] ?? '');

			if (!$degree && !$school) continue;

			$degree_name = $degree;
			if ($year) {
				$degree_name .= ', ' . $year;
			}

			$cred = [
				'@type' => 'EducationalOccupationalCredential',
				'name' => $degree_name,
			];

			if ($level && isset($level_map[$level])) {
				$cred['credentialCategory'] = $level_map[$level];
			}

			$credentials[] = $cred;

			if ($school) {
				$org = [
					'@type' => 'EducationalOrganization',
					'name' => $school,
				];

				if ($city) {
					$org['address'] = [
						'@type' => 'PostalAddress',
						'addressLocality' => $city
					];
				}

				$alumni[] = $org;
			}
		}
	}

	if (!empty($credentials)) {
		$person['hasCredential'] = count($credentials) === 1 ? $credentials[0] : $credentials;
	}

	if (!empty($alumni)) {
		$person['alumniOf'] = count($alumni) === 1 ? $alumni[0] : $alumni;
	}

	if (!empty($modalities) && is_array($modalities)) {
		$offers = [];

		foreach ($modalities as $i => $term_id) {
			$term = get_term($term_id);
			if (!$term || is_wp_error($term)) continue;

			$modality_name = $term->name;

			$offer = [
				'@type' => 'Offer',
				'priceCurrency' => $currency_code,
				'url' => $booking_url,
				'seller' => ['@id' => $permalink . '#person'],
				'itemOffered' => [
					'@type' => 'Service',
					'@id' => $permalink . '#therapy-service-' . ($i + 1),
					'name' => $modality_name,
					'provider' => ['@id' => $permalink . '#person'],
					'serviceType' => $modality_name,
				]
			];

			if ($min_price && $max_price) {
				$offer['priceSpecification'] = [
					'@type' => 'PriceSpecification',
					'priceCurrency' => $currency_code,
					'minPrice' => floatval($min_price),
					'maxPrice' => floatval($max_price),
				];
			}

			if (!empty($regions)) {
				$offer['areaServed'] = array_map(function ($region) {
					return [
						'@type' => 'Place',
						'address' => [
							'@type' => 'PostalAddress',
							'addressRegion' => $region
						]
					];
				}, $regions);
			}

			$offers[] = $offer;
		}

		$person['hasOfferCatalog'] = [
			'@type' => 'OfferCatalog',
			'name' => 'Service Offerings',
			'itemListElement' => $offers,
		];
	}

	$schema['@graph'][] = $person;

	$output = '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
	set_transient($cache_key, $output, 0);
	echo $output;
}

add_action('save_post_expert', function ($post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	delete_transient('expert_schema_' . $post_id);
});

add_filter('acf/load_field/key=field_6894a8586dcd9', function ($field) {
	$field['max'] = intval(date('Y'));
	return $field;
});



add_filter('rank_math/json_ld', function ($data, $jsonld) {
	if (!is_single() || get_post_type() !== 'post') {
		return $data;
	}

	$post_id = get_the_ID();
	$transient_key = "citations_jsonld_{$post_id}";

	$citations_schema = get_transient($transient_key);
	if ($citations_schema === false) {
		$citations = get_field('citations', $post_id);
		$citations_schema = [];

		if ($citations) {
			foreach ($citations as $citation) {
				$type_map = [
					'journal'    => 'ScholarlyArticle',
					'book'       => 'Book',
					'report'     => 'Report',
					'website'    => 'WebPage',
					'internal'   => 'CreativeWork',
					'manuscript' => 'Manuscript',
					'other'      => 'CreativeWork',
				];

				$type = $type_map[$citation['citation_type']] ?? 'CreativeWork';
				$entry = [
					'@type'       => $type,
					'name'        => $citation['title'] ?? '',
					'author'      => $citation['formatted_authors'] ?? '',
					'datePublished' => $citation['publication_year'] ?? '',
				];

				if (!empty($citation['journal_name'])) {
					$periodical = [
						'@type' => 'Periodical',
						'name'  => $citation['journal_name'],
					];

					if (!empty($citation['volume'])) {
						$periodical = [
							'@type' => 'PublicationVolume',
							'volumeNumber' => $citation['volume'],
							'isPartOf' => $periodical,
						];
					}

					if (!empty($citation['issue'])) {
						$periodical = [
							'@type' => 'PublicationIssue',
							'issueNumber' => $citation['issue'],
							'isPartOf' => $periodical,
						];
					}

					$entry['isPartOf'] = $periodical;
				}

				if (!empty($citation['pages'])) {
					$entry['pagination'] = $citation['pages'];
				}

				if (!empty($citation['publisher'])) {
					$entry['publisher'] = [
						'@type' => 'Organization',
						'name'  => $citation['publisher'],
					];
				}

				if (!empty($citation['url'])) {
					$entry['url'] = esc_url($citation['url']);
				}

				if (!empty($citation['doi'])) {
					$doi = trim($citation['doi']);
					if (stripos($doi, '10.') === 0) {
						$entry['identifier'] = [
							'@type' => 'PropertyValue',
							'propertyID' => 'DOI',
							'value' => $doi,
						];
						$entry['sameAs'] = 'https://doi.org/' . $doi;
					} elseif (filter_var($doi, FILTER_VALIDATE_URL)) {
						$entry['sameAs'] = $doi;
					}
				}

				if (!empty($citation['notes'])) {
					$entry['description'] = $citation['notes'];
				}

				$citations_schema[] = $entry;
			}
		}

		// Cache for 0 seconds (until updated)
		set_transient($transient_key, $citations_schema, 0);
	}

	if (!empty($citations_schema)) {
		$data['citation'] = $citations_schema;
	}

	return $data;
}, 99, 2);

// Clear transient when post is saved
add_action('acf/save_post', function ($post_id) {
	if (get_post_type($post_id) === 'post') {
		delete_transient("citations_jsonld_{$post_id}");
	}
}, 20);

add_filter('rank_math/json_ld', function ($data, $jsonld) {
	if (!is_page(1614)) return $data;

	$data[] = [
		"@context" => "https://schema.org",
		"@type" => "WebPage",
		"@id" => "https://offlinenow.com/editorial-board/#webpage",
		"url" => "https://offlinenow.com/editorial-board/",
		"name" => "Editorial Board",
		"description" => "Offline Now’s content is written and reviewed by a team of licensed therapists, social workers, and medical professionals to support people navigating digital habits, mental health, and wellness.",
		"publisher" => [
			"@type" => "Organization",
			"name" => "Offline Now",
			"url" => "https://offlinenow.com",
			"logo" => [
				"@type" => "ImageObject",
				"url" => "https://offlinenow.com/wp-content/uploads/2025/06/logo.svg"
			]
		],
		"audience" => [
			"@type" => "Audience",
			"audienceType" => "General Public",
			"description" => "Individuals seeking guidance around digital wellness, mental health, and therapy-related topics."
		],
		"reviewedBy" => [
			"@type" => "Organization",
			"name" => "Offline Now Editorial Team",
			"description" => "A team of licensed therapists, social workers, and contributors who review content for accuracy and alignment with mental wellness goals."
		]
	];

	return $data;
}, 20, 2);
