<?php
/** @file
 * LTable class. 
 * 
 * Written by Peter, 
 * mailto:petro@petro.ws 
 * http://petro.ws/projects/web/php-light-classes/
 */

 class ltable
{
	public $cols 		= 1;			# Cols number (width)
	public $sp		= 1;			# CellSpacing option
	public $pad		= 3;			# CellPadding option
	public $width		= '100%';		# Table width
	public $border		= 0;			# Border
	public $table_style	= false;		# Table 'style' tag
	public $class_bg 	= 'tablegen_bg';	# Class for background
	public $class_table 	= 'tablegen_table';	# Class for table at all
	public $class_row_1	= 'tablegen_row1';	# Row class 1
	public $class_row_2	= 'tablegen_row2';	# Row class 1
	public $class_row_th	= 'tablegen_th';	# Row class th
	public $class_row_sep	= 'tablegen_sep';	# Row class separator
	public $td_def_align	= 'center';		# Default align in TD
	public $td_def_valign	= 'middle';		# Default valign in TD
	
	public $bg 		= true;			# Has border/background DIV tag?

	public $th_top		= true;			# Add titles to Top
	public $th_bottom	= true;			# Add titles to Bottom

	public $noutems_auto	= true;			# Add 'No items to show' auto, when no items added
	
	# Internal
	private $table_data = array ();
	private $table_th_data = array ();
	private $tabe_tr_params = array ();
	
	function add_row_simple ()
	{
		$params = func_get_args();
		if (!$params)	return;
		$new_data = array ();
		foreach ($params as $param)
		{
			$new_data[] = array ('data'=>$param);
		}
		$this->table_data[] = $new_data;
	}

	function add_sep_row ($text)
	{
		$this->table_data[][0] = array ('data'=>$text,'colspan'=>count($this->table_th_data),'class'=>$this->class_row_sep);
	}
	
	function add_tr_params ($params)
	{
		$this->table_tr_params[count($this->table_data)-1] = $params;
	}
	
	function add_th ($title, $width=false, $align=false, $class=false)
	{
		$new_th = array ();
		$new_th['title']	= $title;
		if ($width)
			$new_th['width'] = $width;
		if ($align)
			$new_th['align'] = $align;
		if ($class)
			$new_th['class'] = $class;
			
		$this->table_th_data[] = $new_th;
	}
	
	function add_no_items ($text=false, $tags=true)
	{
		if (!$text && function_exists('lang')) $text = lang('noitems');
		if ($tags)	$text = '<div style="text-align:center;padding:15px;">'.$text.'</div>';
		$this->table_data[][] = array ('data'=>$text, 'colspan'=>count($this->table_th_data), 'align'=>'center');
	}
	
	function get_table ()
	{
		if ($this->noutems_auto && !count($this->table_data))
			$this->add_no_items();

		$out = "\n";
		# Background ?
		if ($this->bg)
			$out .= '<table border="0" cellpadding="0" cellspacing="0" width="'.$this->width.'"><tr><td class="'.$this->class_bg.'">'."\n";
			
		# Fill The Header
		$out .= '<table border="'.$this->border.'" width="'.$this->width.'" cellpadding="'.$this->pad.'" cellspacing="'.$this->sp.'"';
		if ($this->class_table) $out .= ' class="'.$this->class_table.'"';
		if ($this->table_style) $out .= ' style="'.$this->table_style.'"';
		$out .= " >\n";
		
		if ($this->th_top) $out .= $this->_get_th();
		
		for ($x=0; $x<count($this->table_data); $x++)
		{
			$row = $this->table_data[$x];
			$row_out = '	<tr';

			if (!@$this->table_tr_params[$x]['class'])
			{
				if ($x%2==0)
				{
					$this->table_tr_params[$x]['class'] = $this->class_row_1;
				} else {
					$this->table_tr_params[$x]['class'] = $this->class_row_2;
				}
			}

			if (@$this->table_tr_params[$x])
				foreach ($this->table_tr_params[$x] as $k=>$v)
					$row_out .= ' '.$k.'="'.$v.'"';
					
			$row_out .= ">\n";
			$row_out = str_replace ('{ROW_CLASS}', @$this->table_tr_params[$x]['class'], $row_out);

			for ($i=0; $i<count($row); $i++)
			{
				if (!isset($row[$i])) continue;
				$row_out .= "\t<td";
				if (!@$row[$i]['align']) $row[$i]['align'] = $this->td_def_align;
				if (!@$row[$i]['valign']) $row[$i]['valign'] = $this->td_def_valign;
				foreach ($row[$i] as $k=>$tag)
				{
					if ($k == 'data') continue;
					$row_out .= ' '.$k.'="'.$tag.'"';
				}
				$row_out .= ">\n";
				$row_out .= "\t\t".@$row[$i]['data'];
				$row_out .= "\n\t</td>\n";
			}
			
			$row_out .= "</tr>\n";
			$out .= $row_out;
		}
		if ($this->th_bottom) $out .= $this->_get_th();
		$out .= "</table>\n";
		if ($this->bg)
			$out .= "</td></tr></table>\n";
		$out .= "\n";
		return $out;
	}
	
	function _get_th ()
	{
		if (!$this->table_th_data) return;
		
		$out = '<tr class="'.$this->class_row_th.'">'."\n";
		for ($x=0; $x<count($this->table_th_data); $x++)
		{
			$out .= '	<th';
			if (@$this->table_th_data[$x]['class']) $out .= ' class="'.$this->table_th_data[$x]['class'].'"';
			if (@$this->table_th_data[$x]['width']) $out .= ' width="'.$this->table_th_data[$x]['width'].'"';
			if (@$this->table_th_data[$x]['align']) $out .= ' align="'.$this->table_th_data[$x]['align'].'"';
			$out .= '>';
			if (@$this->table_th_data[$x]['title']) $out .= $this->table_th_data[$x]['title'];
			$out .= "</th>\n";
		}
		$out .= "</tr>\n";
		return $out;
	}
}
?>