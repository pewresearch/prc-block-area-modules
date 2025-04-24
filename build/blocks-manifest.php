<?php
// This file is generated. Do not modify it manually.
return array(
	'block-area' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-platform/block-area',
		'version' => '3.0.1',
		'title' => 'Block Area',
		'description' => 'A block area is like a template part but with greater editorial control.',
		'category' => 'theme',
		'keywords' => array(
			'block area',
			'block module',
			'topic lede',
			'featured lede'
		),
		'attributes' => array(
			'ref' => array(
				'type' => 'integer'
			),
			'blockAreaSlug' => array(
				'type' => 'string'
			),
			'taxonomyName' => array(
				'type' => 'string'
			),
			'taxonomyTermSlug' => array(
				'type' => 'string'
			),
			'inheritTermFromTemplate' => array(
				'type' => 'boolean'
			)
		),
		'example' => array(
			'attributes' => array(
				'blockAreaSlug' => 'topic-lede',
				'taxonomyName' => 'category',
				'taxonomyTermSlug' => 'climate-energy-environment'
			),
			'viewportWidth' => 1200
		),
		'supports' => array(
			'anchor' => true,
			'html' => false,
			'interactivity' => true
		),
		'usesContext' => array(
			'queryId',
			'query',
			'queryContext',
			'templateSlug',
			'previewPostType'
		),
		'textdomain' => 'prc-block-area',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	),
	'block-area-context-provider' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-platform/block-area-context-provider',
		'version' => '3.0.1',
		'title' => 'Block Area Context Provider',
		'description' => 'Collect the Story Item IDs from all block areas and provide them to query blocks to prevent story repetition.',
		'category' => 'theme',
		'keywords' => array(
			'block area',
			'block module',
			'topic lede',
			'featured lede'
		),
		'supports' => array(
			'anchor' => true,
			'html' => false
		),
		'usesContext' => array(
			'postId',
			'postType',
			'queryId',
			'query',
			'queryContext',
			'templateSlug',
			'previewPostType'
		),
		'textdomain' => 'prc-platform-area-context-provider',
		'editorScript' => 'file:./index.js'
	)
);
