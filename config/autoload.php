<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package Wp_uuid_create
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'UUIDCreator',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'UUIDCreator\UUIDCreatorClass' => 'system/modules/wp_uuid_creator/classes/UUIDCreatorClass.php',
	'UUIDCreator\UUIDWorker'       => 'system/modules/wp_uuid_creator/classes/UUIDWorker.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'be_uuid_creator' => 'system/modules/wp_uuid_creator/templates',
));
