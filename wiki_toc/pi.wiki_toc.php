<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2006 - 2015 EllisLab, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
ELLISLAB, INC. BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name of EllisLab, Inc. shall not be
used in advertising or otherwise to promote the sale, use or other dealings
in this Software without prior written authorization from EllisLab, Inc.
*/


/**
 * Wiki TOC Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			EllisLab
 * @copyright		Copyright (c) 2004 - 2015, EllisLab, Inc.
 * @link			https://github.com/EllisLab/Wiki-Toc
 */
class Wiki_toc {

	/* -------------------------------------
	/*  User Modifiable Class Variables
	/* -------------------------------------*/

	public $formatting		= 'default';		// Formatting plugin you wish to use, 'markdown', 'textile', etc.
	public $toc_tag		= '[TOC]';				// Tag you wish to use in your articles to place the table of contents
	public $heading		= 'Table of Contents';	// Heading you'd like the table of contents to have
	public $separator		= '<hr />';				// XHTML to separate the table of contents from the article

	/* -------------------------------------
	/*  Do not edit below this point
	/* -------------------------------------*/

	public $return_data	= '';
	public $toc			= '';
	public $last			= 0;
	public $tabs			= '';
	public $ids			= array();
	public $marker			= 'ae7d69f8b295448108acb2280d484cb93f29b1da';

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	function __construct($str = '')
	{
		if ($str == '')
		{
			ee()->TMPL->log_item("ERROR: Wiki_toc plugin---Can only be used as a formatting plugin.");
			return $this->return_data = ee()->TMPL->tagdata;
		}

		// replace our TOC tag with a marker so it's not trashed by the chosen formatting plugin
		// and protect it from getting <p> tags around it.
		$str = str_replace($this->toc_tag, "<div>{$this->marker}</div>", $str);

		/* -------------------------------------
		/*  Apply Text Formatting
		/* -------------------------------------*/

		ee()->load->library('typography');
		ee()->typography->parse_smileys = FALSE;
		$fmt = ($this->formatting == 'default') ? 'xhtml' : strtolower($this->formatting);

		if ( ! isset(ee()->typography->text_fmt_plugins[$fmt]))
		{
			ee()->TMPL->log_item("ERROR: Wiki_toc plugin---Invalid formatting plugin specified.");
			return $this->return_data = str_replace($this->marker, $this->toc_tag, $str);
		}

		$fmt = ($fmt == 'xhtml') ? 'auto_typography' : $fmt;
		$str = ee()->typography->$fmt($str);

		/* -------------------------------------
		/*  Work the magic
		/* -------------------------------------*/

		$str = preg_replace_callback("/\<h([1-6])\>(.+?)\<\/h\\1\>/siu", array(&$this, 'create_toc'), $str);

		/* -------------------------------------
		/*  Get the output ready
		/* -------------------------------------*/

		$this->toc .= "</li>\n";

		// close any unclosed tags
		for ($i = count(explode("\t", $this->tabs)) - 1; $i > 1; $i--)
		{
			$this->toc .= substr($this->tabs, 0, $i - 1)."</ul>\n"; $i--;
			$this->toc .= substr($this->tabs, 0, $i - 1)."</li>\n";
		}

		$this->toc .= "</ul>\n{$this->separator}\n";

		/* -------------------------------------
		/*  Swap marker with finalized TOC
		/* -------------------------------------*/

		$str = str_replace('<div>'.$this->marker.'</div>', $this->toc, $str);

		// clean up a few stray tag patterns that may have cropped up due to
		// certain formatting plugin quirks
		$str = str_replace('<p></p>', '', $str);
		$str = str_replace('<p><p>', '<p>', $str);
		$str = str_replace('<p><br />', '<p>', $str);

		return $this->return_data = $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Create heading id's and TOC
	 *
	 * @access	public
	 * @param	mixed	regex match array
	 * @return	string	heading / toc
	 */
	function create_toc($matches)
	{
		$first_run = FALSE;

		// First time here, get the TOC started
		if ($this->last == 0)
		{
			$this->toc .= "<p><strong>{$this->heading}</strong></p>\n<ul id='toc' title='{$this->heading}'>\n";
			$this->tabs = "\t";
			$this->last = $matches[1];
			$first_run = TRUE;
		}

		/* -------------------------------------
		/*  Add / close preceding tags
		/* -------------------------------------*/

		$diff = $matches[1] - $this->last;

		if ($diff > 0)
		{
				$this->tabs = $this->tabs."\t";
				$this->toc .= "\n{$this->tabs}<ul>\n";
				$this->tabs = $this->tabs."\t";
		}
		elseif ($diff < 0)
		{
			$this->toc .= "</li>\n";

			// loop so we close properly when going from e.g. h6 to h4
			for ($i = 0; $i < abs($diff); $i++)
			{
				$this->tabs = substr($this->tabs, 0, -1);
				$this->toc .= "{$this->tabs}</ul>\n";
				$this->tabs = substr($this->tabs, 0, -1);
				$this->toc .= "{$this->tabs}</li>\n";
			}
		}
		elseif ($first_run !== TRUE)
		{
			$this->toc .= "</li>\n";
		}

		/* -------------------------------------
		/*  Create the id, make sure it's unique
		/* -------------------------------------*/

		$id	= $this->clean_id($matches[2]);

		if (in_array($id, $this->ids))
		{
			$count = 0;

			foreach ($this->ids as $key => $val)
			{
				$count = (stristr($val, $id)) ? $count + 1 : $count;
			}

			$id = $id.'_'.$count;
		}

		$this->ids[] = $id;

		/* -------------------------------------
		/*  Add to the TOC and return the new heading
		/* -------------------------------------*/

		$safe_anchor = str_replace('%', '.', urlencode($id));

		$this->toc .= "{$this->tabs}<li><a href='#".$safe_anchor."'>$matches[2]</a>";
		$this->last = $matches[1];

		return "<h{$matches[1]} id='{$safe_anchor}'>{$matches[2]}</h{$matches[1]}>";
	}

	// --------------------------------------------------------------------

	/**
	 * Clean the id of bad characters
	 *
	 * @access	public
	 * @param	string	dirty text
	 * @return	string	cleaned text
	 */
	function clean_id($str)
	{

		// Remove all entities
		$str = preg_replace('/&#x([0-9a-f]{2,5});{0,1}|&#([0-9]{2,4});{0,1}/', '', $str);
		$str = preg_replace('/&.*?;/', '', $str);

		if (ee()->config->item('word_separator') == 'dash')
		{
			$trans = array(
							"#_#"									=> '-',
							"#['\"\?*\$,=\(\)\[\]]#"				=> '',
							"#\s+#"									=> '-',
							"#[^a-z0-9\-\_@&\'\"!\.:\+\xA1-\xFF]#i"	=> '',
							"#\-+#"									=> '-',
							"#\-$#"									=> '',
							"#^\-#"									=> ''
						   );
		}
		else
		{
			$trans = array(
							"#['\"\?*\$,=\(\)\[\]]#"				=> '',
							"#\s+#"									=> '_',
							"#[^a-z0-9\-\_@&\'\"!\.:\+\xA1-\xFF]#i"	=> '',
							"#_+#"									=> '_',
							"#_$#"									=> '',
							"#^_#"									=> '',
							"#\-+#"									=> '-',
						   );
		}

		return preg_replace(array_keys($trans), array_values($trans), urldecode($str));
	}

	// --------------------------------------------------------------------
}
