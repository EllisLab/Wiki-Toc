<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
  'pi_name'			=> 'Wiki Table of Contents',
  'pi_version'		=> '1.3',
  'pi_author'		=> 'Derek Jones',
  'pi_author_url'	=> 'http://expressionengine.com/',
  'pi_description'	=> 'Adds a Table of Contents to your Wiki articles',
  'pi_usage'		=> Wiki_toc::usage()
  );

/**
 * Wiki TOC Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			ExpressionEngine Dev Team
 * @copyright		Copyright (c) 2006 - 2009, EllisLab, Inc.
 * @link			http://expressionengine.com/downloads/details/wiki_table_of_contents/
 */
class Wiki_toc {

	/* -------------------------------------
	/*  User Modifiable Class Variables
	/* -------------------------------------*/
	
	var $formatting		= 'default';		// Formatting plugin you wish to use, first letter capitalized. e.g. 'Markdown', 'Textile', etc.
	var $toc_tag		= '[TOC]';				// Tag you wish to use in your articles to place the table of contents
	var $heading		= 'Table of Contents';	// Heading you'd like the table of contents to have
	var $separator		= '<hr />';				// XHTML to separate the table of contents from the article

	/* -------------------------------------
	/*  Do not edit below this point
	/* -------------------------------------*/
	
	var $return_data	= '';
	var $toc			= '';
	var $last			= 0;
	var $tabs			= '';
	var $ids			= array();
	var $marker			= 'ae7d69f8b295448108acb2280d484cb93f29b1da';

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	function Wiki_toc($str = '')
	{
		$this->EE =& get_instance();
		
		if ($str == '')
		{
			$this->EE->TMPL->log_item("ERROR: Wiki_toc plugin---Can only be used as a formatting plugin.");
			return $this->return_data = $this->EE->TMPL->tagdata;
		}
		
		// replace our TOC tag with a marker so it's not trashed by the chosen formatting plugin
		// and protect it from getting <p> tags around it.
		$str = str_replace($this->toc_tag, "<div>{$this->marker}</div>", $str);
		
		/* -------------------------------------
		/*  Apply Text Formatting
		/* -------------------------------------*/
		
		if ($this->formatting != 'default')
		{
			if ( ! class_exists($this->formatting))
			{
				require PATH_THIRD.strtolower($this->formatting).'/pi.'.strtolower($this->formatting).EXT;
			}
			
			// make sure the specified plugin has a constructor to parse the article
			if (method_exists($this->formatting, $this->formatting))
			{
				$class = $this->formatting;
				$TYPE = new $class($str);
				$str = $TYPE->return_data;
			}
			else
			{
				$this->EE->TMPL->log_item("ERROR: Wiki_toc plugin---Invalid formatting plugin specified.");
				return $this->return_data = str_replace($this->marker, $this->toc_tag, $str);
			}
		}
		else
		{
			$this->EE->load->library('typography');
			$this->EE->typography->parse_smileys = FALSE;
			$str =  $this->EE->typography->xhtml_typography($str);
		}
		
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
		
		if ($this->EE->config->item('word_separator') == 'dash')
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
	
	/**
	 * Plugin Usage
	 *
	 * @access	public
	 * @return	string	plugin usage text
	 */
	function usage()
	{
		ob_start(); 
		?>
		NOTE: This plugin requires ExpressionEngine 1.5 or greater and PHP 4.3.2 or greater.
		
		There are four user settings in the pi.wiki_toc.php file that you use to control the
		plugin settings.
		
		$formatting
		-----------------
		This setting controls which formatting plugin will be used to control the text formatting for
		your articles.  You must use the class name of the plugin, which will always have the first
		letter capitalized, and it will match the plugin filename, without the pi. and .php portions.
		
		e.g.: the Textile plugin file is named pi.textile.php and the class name is Textile
		
		The default setting is 'default', and uses ExpressionEngine's XHTML Typography class.
		
		$toc_tag
		-----------------
		This setting controls the tag that you will use in your articles to place the Table of Contents.
		If you change this, use a unique combination of characters that won't be  encountered in the
		body of any of your articles.  The default is '[TOC]'
		
		$heading
		-----------------
		This setting is to allow for easy localization of the plugin.  This will be used as a
		label for your table of contents.  The default is 'Table of Contents'.
		
		$separator
		-----------------
		This setting contains the XHTML markup that will be placed as a separator between the table of
		contents and the article.  The default markup is a horizontal rule: <hr />.
		
		
		-----------------
		     USAGE:
		-----------------
		Select "Wiki TOC" as your formatting option in your Wiki Preferences.
		
		Place your table of contents tag at the top of an article that you wish to have a table of contents.
		
		[TOC]
				
		Create headings with [h#] or <h#> tags that will generate the jump points
		
		[h4]Your Heading[/h4]
		[h5]Your Subheading[/h5]
		
		The plugin will format your article with your preferred formatting plugin, and create a table of contents
		in place of your [TOC] tag, in the form of an HTML unordered list.  The list is given an id="toc" to
		facilitate styling via CSS.
		
		
		-----------------
		    CHANGELOG:
		-----------------
		Version 1.1 - Added support for UTF-8 headings.  Requires PHP 4.3.2 (or 4.2 with the PCRE library
			compiled correctly).
			
		Version 1.2 - Modified for compatibility with the current typography class (EE 1.6.5+).

		Version 1.3 - Updated plugin to be 2.0 compatible
					
		<?php
		$buffer = ob_get_contents();
		
		ob_end_clean();
		
		return $buffer;
	}
	/* END */
	
}
// END CLASS

/* End of file pi.wiki_toc.php */
/* Location: ./system/expressionengine/third_party/wiki_toc/pi.wiki_toc.php */