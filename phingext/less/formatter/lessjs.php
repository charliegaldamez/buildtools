<?php
/**
 * @package			TwentronixBuildTools
 * @author			Jurian Even
 * @link			https://www.twentronix.com
 * @copyright		Copyright (C) 2012 - 2014 Twentronix. All rights reserved.
 * @license			GNU GPL version 3 or later <http://www.gnu.org/licenses/gpl.html>
 */

/**
 * This class is taken verbatim from:
 *
 * @package     FrameworkOnFramework
 * @subpackage  less
 * @copyright   Copyright (C) 2010 - 2014 Akeeba Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Which is taken on verbatim from:
 *
 * lessphp v0.3.9
 * http://leafo.net/lessphp
 *
 * LESS css compiler, adapted from http://lesscss.org
 *
 * Copyright 2012, Leaf Corcoran <leafot@gmail.com>
 * Licensed under MIT or GPLv3, see LICENSE
 */
class TxbtLessFormatterLessjs extends TxbtLessFormatterClassic
{
	public $disableSingle = true;

	public $breakSelectors = true;

	public $assignSeparator = ": ";

	public $selectorSeparator = ",";
}
