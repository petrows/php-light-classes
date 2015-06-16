<?php
/** @file
 * LForm - form generator class
 * 
 * Written by Peter, 
 * mailto:petro@petro.ws 
 * http://petro.ws/projects/web/php-light-classes/
 */
 
class lform
{
	public $out = '';
	public $d_inp = array ();
	public $d_add = array ();
	public $d_val = array ();
	public $row_params = array ();
	public $title_params = array ();
	public $title_width = 150;
	public $c_id = 0;
	public $g_r_current = 0;
	public $enctype	= 'multipart/form-data';
	public $method	= 'post';

	public $class_bg 		= 'formgen_bg';
	public $class_table 	= 'formgen_table';
	public $class_row 		= 'formgen_row';
	public $class_title 	= 'formgen_title';
	public $class_input_ar	= 'formgen_input_area';
	public $class_input		= 'formgen_input';
	public $class_checkbox	= 'formgen_checkbox';
	public $class_textarea	= 'formgen_textarea';
	public $class_select	= 'formgen_select';
	public $class_button	= 'formgen_button';
	public $class_submit	= 'formgen_submit';
	public $class_paragraph	= 'formgen_paragraph';
	public $class_row_comment	= 'formgen_row_comment';
	public $class_inp_comment	= 'formgen_row_comment';

	public $tpl_row_start  = '<tr {params}>';
	public $tpl_row_end  	= '</tr>';
	public $tpl_title		= '<td {params}> {title} : {comment}</td>';
	public $tpl_input_area = '<td {params}>{input}</td>';

	public $style_def_1 	= 'width:98%';

	public $input = array ();

	function __construct($use_input = false)
	{
		if (!$use_input)
			$this->input = $_POST;
		else
			$this->input = $use_input;
	}
	
	function use_input($use = true)
	{
		if (!$use)
		{
			$this->input = array();
		}
	}

	function add_input ($type, $title=false, $name=false, $value=false, $inp_ad=false)
	{
		$this->c_id++;
		$this->d_inp[] = array('title'=>$title, 'type'=>'std', 'input'=>array(array('type'=>$type, 'name'=>$name, 'value'=>$value, 'inp_ad'=>$inp_ad, 'c_id'=>$this->c_id)));
	}
	
	function add_checkbox ($title, $name, $checked=false, $add_text=false)
	{
		$this->add_input ('checkbox', $title, $name, $checked, $inp_ad=false);
		if ($add_text)
			$this->add_input_comment ($add_text);
	}
	
	function add_text ($title, $name, $value, $rows=false, $cols=false)
	{
		$this->add_input ('textarea', $title, $name, $value);
		$add = false;
		if ($rows) $add['rows'] = $rows;
		if ($cols) $add['cols'] = $cols;
		if ($add) $this->add_input_params ($add);
	}
	
	function add_select ($title, $name, $values, $def_select=false)
	{
		$this->add_input ('select', $title, $name, $def_select, array('values'=>$values));
	}
	
	function add_title ($title)
	{
		$this->add_input ('title', $title);
	}
	
	function add_paragraph ($title)
	{
		$this->add_input ('paragraph', $title);
	}

	function add_submit($title, $name)
	{
		$this->add_input ('submit', '', $name, $title);
	}
	
	function add_html($data)
	{
		$this->add_input('html', $data);
	}
	
	function add_raw($data)
	{
		$this->add_input('raw', $data);
	}

	function add_custom ($title=false, $text=false)
	{
		$this->add_input ('custom', $title, '', $text);
	}

	function append_last ()
	{
		if (count($this->d_inp)<2) return;
		$last_id = count($this->d_inp)-1;
		$prev_id = count($this->d_inp)-2;

		$last = $this->d_inp[$last_id]['input'];
		if (!$last) return;
		$prev = $this->d_inp[$prev_id]['input'];
		if (!$prev) return;

		$this->d_inp[$prev_id]['input'] = array_merge($prev,$last);

		array_pop ($this->d_inp);
		reset($this->d_inp);
	}

	function add_input_params ($params, $id=false)
	{
		if (!$id) $id = $this->c_id;
		$this->d_add[$id] = $params;
	}
	
	function add_title_params ($params, $id=false)
	{
		if (!$id) $id = count($this->d_inp)-1;
		$this->title_params[$id] = $params;
	}
	
	function add_input_comment ($comment, $id=false)
	{
		if (!$id) $id = $this->c_id;
		# Search this
		for ($x=0; $x<count($this->d_inp); $x++)
		{
			for ($n=0; $n<count($this->d_inp[$x]['input']); $n++)
			{
				if ($this->d_inp[$x]['input'][$n]['c_id'] == $id)
				{
					$this->d_inp[$x]['input'][$n]['comment_1'] = $comment;
					return;
				}
			}
		}
	}
	
	function add_row_comment ($comment)
	{
		# Search this
		$this->d_inp[count($this->d_inp)-1]['row_comment'] = $comment;
	}

	function add_row_params ($params)
	{
		foreach ($params as $k=>$v)
		{
			$this->row_params[count($this->d_inp)-1][$k] = $v;
		}
	}
	
	function row_nobr ()
	{
		$this->row_params[count($this->d_inp)-1]['no_br'] = true;
	}

	function load_class ($input)
	{
		switch ($input)
		{
			case 'text':
				return $this->class_input;
			case 'password':
				return $this->class_input;
			case 'textarea':
				return $this->class_textarea;
			case 'checkbox':
				return $this->class_checkbox;
			case 'select':
				return $this->class_select;
			case 'submit':
				return $this->class_button;
			case 'button':
				return $this->class_button;
			case 'paragraph':
				return $this->class_paragraph;

			default:
				return $this->class_input;
		}
	}

	function get_input ($input)
	{		
		if ($input['type'] == 'text' || $input['type'] == 'password')
			return '<input type="'.$input['type'].'" name="'.@$input['name'].'" id="'.@$input['name'].'" '.$this->load_params($input).' value="'.$this->load_value($input).'" />';
		
		if ($input['type'] == 'submit')
			return '<input type="'.$input['type'].'" name="'.@$input['name'].'" id="'.@$input['name'].'" '.$this->load_params($input).' value="  '.$input['value'].'  " />';

		//dbg ($input);
		if ($input['type'] == 'textarea')
			return '<textarea name="'.@$input['name'].'" id="'.@$input['name'].'" '.$this->load_params($input).'>'.$this->load_value($input).'</textarea>';

		if ($input['type'] == 'checkbox')
		{
			$out = '';
			if (@$input['comment_1'])
			{
				$out .= '<label>';
			}
			$out .= '<input type="checkbox" name="'.@$input['name'].'" id="'.@$input['name'].'" '.$this->load_params($input).' value="ON" ';
			if ($this->load_value($input)) $out .= 'checked="checked" ';
			$out .= '/>';
			if (@$input['comment_1'])
			{
				$out .= ' '.$input['comment_1'] . '</label>';
			}
			return $out;
		}
		
		if ($input['type'] == 'select')
		{
			$out = '';
			$val = $this->load_value($input);
			
			$out .= '<select name="'.@$input['name'].'" id="'.@$input['name'].'" '.$this->load_params($input).' >';
			foreach ($input['inp_ad']['values'] as $k=>$v)
			{
				$out .= '<option value="'.$k.'"'.($val==$k?' selected="selected"':'').'>'.$v.'</option>';
			}
			
			$out .= '</select> ';
			
			if (@$input['comment_1'])
			{
				$out .= ' <span class="'.$this->class_inp_comment.'">'.$input['comment_1'].'</span>';
			}
			
			return $out;
		}
		
		return '<input type="'.$input['type'].'" name="'.@$input['name'].'" id="'.@$input['name'].'" '.$this->load_params($input).' value="'.$this->load_value($input).'" />';		
	}

	function load_params ($input)
	{
		$type = $input['type'];
		$id = $input ['c_id'];
		$set_style_1 = false;
		if (in_array($type,array('textarea','text','password')))
			$set_style_1 = true;
		$set_style_2 = false;
		if (in_array($type,array('submit','button')))
			$set_style_2 = true;
		//dbg ($this->d_add[$id]);

		if (!isset($this->d_add[$id]) && !$set_style_1) 
			if (!$set_style_2) { return; }

		if (!isset($this->d_add[$id]['style']) && $set_style_1 && !$set_style_2)
			$this->d_add[$id]['style'] = $this->style_def_1;

		if (!isset($this->d_add[$id]['class']))
			$this->d_add[$id]['class'] = $this->load_class($type);


		$out = '';
		foreach ($this->d_add[$id] as $k=>$v)
		{
			$out .= $k . '="' . $v . '" ';
		}
		return $out;
	}

	function load_value ($input)
	{
		# Checkbox input
		if ($input['type'] == 'checkbox')
		{
			if (isset($this->input[$input['name']]))
			{
				$this->d_val[$input['c_id']] = true;
				return true;
			}
			if (empty($this->input))
			{
				$this->d_val[$input['c_id']] = $input['value'];
				return $input['value'];
			}
			if (!isset($this->input[$input['name']]) && !empty($this->input))
			{
				$this->d_val[$input['c_id']] = false;
				return false;
			}
		}

		# Text input - text, textfield, etc..
		if ($input['type'] == 'text' || $input['type'] == 'textarea' || $input['type'] == 'password' || $input['type'] == 'select')
		{
			if (!isset($this->input[$input['name']]))
			{
				$this->d_val[$input['c_id']] = $input['value'];
			} else {
				$this->d_val[$input['c_id']] = htmlspecialchars($this->input[$input['name']]);
			}
		}

		if (!isset($this->d_val[$input['c_id']])) $this->d_val[$input['c_id']] = @$input['value'];

		return @$this->d_val[$input['c_id']];
	}
	
	function params2str ($params)
	{
		$out = '';
		foreach ($params as $k=>$v)
			$out .= $k . '="' . $v . '" ';
		return $out;
	}
	
	function tpl_row_start ($id)
	{
		if (!isset($this->row_params[$id]['class']))
		{
			$this->row_params[$id]['class'] = $this->class_row;
		}
		
		$tpl = $this->tpl_row_start;
		$tpl = str_replace('{params}', $this->params2str ($this->row_params[$id]), $tpl);
		return $tpl . "\n";
	}
	
	function tpl_row_end ()
	{
		return $this->tpl_row_end . "\n";
	}
	
	function tpl_title ($id)
	{
		$tpl = $this->tpl_title;
		
		if (@$this->d_inp[$id]['row_comment'])
			$tpl = str_replace('{comment}', '<div class="'.$this->class_row_comment.'" >'.$this->d_inp[$id]['row_comment'].'</div>', $tpl);
		else
			$tpl = str_replace('{comment}', '', $tpl);
		$tpl = str_replace('{title}', @$this->d_inp[$id]['title'], $tpl);
		
		# Params
		if (!isset($this->title_params[$id]['width']) && $this->title_width)
		{
			$this->title_params[$id]['width'] = $this->title_width;
		}
		if (!isset($this->title_params[$id]['class']))
		{
			$this->title_params[$id]['class'] = $this->class_title;
		}
		$tpl = str_replace('{params}', $this->params2str ($this->title_params[$id]), $tpl);
		return $tpl . "\n";
	}

	function get_row ($id=false)
	{
		if (!$id) $id = $this->g_r_current;
		
		$out = '';
		
		# HTML
		if ($this->d_inp[$id]['input'][0]['type'] == 'html')
		{
			return "\n" . $this->d_inp[$id]['title'] . "\n";
			# continue;
		}

		# RAW
		if ($this->d_inp[$id]['input'][0]['type'] == 'raw')
		{
			$out .= $this->tpl_row_start($id);
			$out .= $this->d_inp[$id]['title'];
			$out .= $this->tpl_row_end();
			return $out;
		}

		# WIDE
		if ($this->d_inp[$id]['input'][0]['type'] == 'wide')
		{
			return "\n" . $this->d_inp[$id]['title'] . "\n";
			# continue;
		}

		$this->out .= $this->tpl_row_start($id);
		
		# Title
		if ($this->d_inp[$id]['input'][0]['type'] == 'title')
		{
			$out .= '<th colspan="2">' . $this->d_inp[$id]['title'] . "</th>\n";
			$out .= $this->tpl_row_end();
			return $out;
		}
		
		# Paragraph
		if ($this->d_inp[$id]['input'][0]['type'] == 'paragraph')
		{
			$out .= '<td colspan="2" class="'.$this->class_paragraph.'">' . $this->d_inp[$id]['title'] . "</td>\n";
			$out .= $this->tpl_row_end();
			return $out;
		}

		# Custom
		if ($this->d_inp[$id]['input'][0]['type'] == 'custom')
		{
			// dbg ($this->d_inp[$id]);
			$out .= $this->tpl_title($id);
			$out .= '<td class="'.$this->class_input_ar.'">'.$this->d_inp[$id]['input'][0]['value'].'</td>';
			$out .= $this->tpl_row_end();
			return $out;
		}

		# Comment
		if ($this->d_inp[$id]['input'][0]['type'] == 'comment')
		{
			// dbg ($this->d_inp[$id]);
			// $out .= $this->tpl_title($id);
			$out .= '<td colspan="2" class="'.$this->class_row_comment.'">'.$this->d_inp[$id]['title'].'</td>';
			$out .= $this->tpl_row_end();
			return $out;
		}

		# Captcha
		if ($this->d_inp[$id]['input'][0]['type'] == 'captcha')
		{
			# Drop old capctha!
			unset ($_SESSION['captcha_keystring']);
			
			# Lang strings
			if (function_exists('lang'))
			{
				$this->d_inp[$id]['title'] = lang('captcha');
				$comm = lang('captcha_t');
			} else {
				$this->d_inp[$id]['title'] = 'Код безопасности';
				$comm = 'Введите буквы и/или цифры, которые Вы видите на картинке.<br/><br/>Нажмите <a href="javascript:void(0);" onclick="document.getElementById(\'captcha_img\').src=\''.URL.'/captcha/?\'+Math.random();">здесь</a>, если Вы не можете прочитать её.';
			}
			$out .= $this->tpl_title($id);
			$out .= '
<td>
	<table width="100%"><tr>
	<td width="150" align="center">
		<img src="'.URL.'/captcha/?'.mt_rand().'" id="captcha_img" style="cursor:pointer;" onclick="document.getElementById(\'captcha_img\').src=\''.URL.'/captcha/?\'+Math.random();">
		<br/>
		<input type="text" autocomplete="off" class="formgen_input" name="captcha" style="margin-top:2px;width:120px;text-transform:lowercase;text-align:center;"/>
	</td><td align="center" style="font-size:10px;">
		'.$comm.'
	</td>
	</tr></table>
</td>';
			$out .= $this->tpl_row_end();
			return $out;
		}

		
		# TextArea+title
		if ($this->d_inp[$id]['input'][0]['type'] == 'textarea' && $this->d_inp[$id]['title'])
		{
			$out .= $this->tpl_title($id);
			$out .= '<td class="'.$this->class_input_ar.'" align="left">'.$this->get_input ($this->d_inp[$id]['input'][0]).'</td>';
			$out .= $this->tpl_row_end();
			return $out;
		}
		# TextArea
		if ($this->d_inp[$id]['input'][0]['type'] == 'textarea')
		{
			$out .= '<td class="'.$this->class_input_ar.'" colspan="2" align="center">'.$this->get_input ($this->d_inp[$id]['input'][0]).'</td>';
			$out .= $this->tpl_row_end();
			return $out;
		}

		# Submit
		if ($this->d_inp[$id]['input'][0]['type'] == 'submit')
		{
			$out .= '<td class="'.$this->class_submit.'" colspan="2" align="center">';
			for ($n=0; $n<count($this->d_inp[$id]['input']); $n++)
			{
				$input = $this->d_inp[$id]['input'][$n];
				$out .= $this->get_input ($input);
			}
			$out .= '</td>'.$this->tpl_row_end();
			return $out;
		}

		# Std
		$out .= $this->tpl_title($id);
		$out .= '<td class="'.$this->class_input_ar.'">';
		for ($n=0; $n<count($this->d_inp[$id]['input']); $n++)
		{
			$input = $this->d_inp[$id]['input'][$n];
			$out .= $this->get_input ($input);
			
			#echo $this->d_inp[$id]['input'][$n]['name'];
			if (!@$this->row_params[$id]['no_br'])
				$out .= '<br/>';
		}
		$out .= '</td>';
		$out .= $this->tpl_row_end();
		
		if (!$id) $this->g_r_current++;		
		return $out;
	}

	function get_form ()
	{
		# Let's start
		$this->out .= '<form method="'.$this->method.'" '.($this->enctype?'enctype="'.$this->enctype.'"':'').'><div class="formgen_bg"><table border="0" width="100%" cellpadding="5" cellspacing="1" class="formgen_table">';

		for ($x=0; $x<count($this->d_inp); $x++)
		{
			if (!is_array(@$this->d_inp[$x]['input']))
			continue;

			$this->out .= $this->get_row($x);
		}

		$this->out .= '</table></div></form>';
		return $this->out;
	}
}

?>