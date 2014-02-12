<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package   UUIDCreator
 * @author    Johannes Pichler
 * @license   LGPL
 * @copyright Webpixels Johannes Pichler 2014
 */


/**
 * Namespace
 */
namespace UUIDCreator;


/**
 * Class BE_CLASS
 *
 * @copyright  Webpixels Johannes Pichler 2014
 * @author     Johannes Pichler
 * @package    Devtools
 */
class UUIDCreatorClass extends \BackendModule
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_uuid_creator';


	/**
	 * Generate the module
	 */
	protected function compile()
	{
            
            $this->Template->content = '';
            $this->Template->href = $this->getReferer(true);
            $this->Template->title = specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']);
            $this->Template->button = $GLOBALS['TL_LANG']['MSC']['backBT'];
            $this->Template->logString = "";
            
            if ( \Input::get('create')){
                $correct_it = new UUIDWorker();
                $correct_it->run();
                $this->Template->logString = $correct_it->logString;
                $this->Template->logStringErrors = $correct_it->logStringErrors;
            }

	}
}
