<?php

session_start();

define ('ROOT_PATH', realpath(dirname(__FILE__).'/../'));

# Define paths for templates and compiled-cache
# Templates:
define('LTPL_DIR', ROOT_PATH.'/example/tpl');
# Templates cache:
define('LTPL_C_DIR', ROOT_PATH.'/example/tmp'); // Should be writeable!

# Config SHOULD be included BEFORE classes include
include ROOT_PATH.'/example/config.php';

# Our effective URL
if (!defined('URL'))
{
	$our_url = "http://".$_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
	define ('URL', $our_url);
}

include ROOT_PATH.'/include/class.lform.php';
include ROOT_PATH.'/include/class.ldb.php';
include ROOT_PATH.'/include/class.ltable.php';
include ROOT_PATH.'/include/class.ltpl.php';

class example_site
{
	public $errors = array();
	
	function show()
	{
		$tpl = new ltpl('index');
		
		$main = ''; # Some out data will be here
		
		switch (@$_GET['p'])
		{
			case 'form':
				$main = $this->test_form();
				break;
			case 'table':
				$main = $this->test_table();
				break;
			case 'genusers':
				$main = $this->test_genusers();
				break;
			default:
				$main = $this->test_main();
		}
		
		# Set var {{main}} to our main data
		$tpl->v('main', $main);		
		
		# Some errors?
		if ($this->errors)
		{
			$errors_text = '';
			foreach ($this->errors as $err)
				$errors_text .= '<li>'.$err.'</li>';
			$tpl->v('errors', $errors_text);
		}
		
		# Show DB debug
		$tpl->v('debug', true);
		$tpl->v('db_debug', ldb_log_html());		
		
		return $tpl->get();
	}
	
	function test_main()
	{
		return '<p>Index page. Select in menu what to test!</p>';
	}
	
	function test_form()
	{
		$data = ldb_select('user', array('id', 'name'));
		
		$tg = new ltable();		
		
		foreach ($data as $row)
		{
			$tg->add_row_simple('ass');
		}
		
		# Return our super table
		return $tg->get_table();
	}
	
	function test_table()
	{
		$data = ldb_select('user', array('id', 'name'));
		
		$tg = new ltable();
		
		# Add table row titles
		$tg->add_th('#', '30');
		$tg->add_th('ID', '30');
		$tg->add_th('Name');
		
		foreach ($data as $x => $row)
		{
			$tg->add_row_simple($x+1, $row['id'], htmlspecialchars($row['name']));
		}
		
		# Return our super table
		return $tg->get_table();
	}
	
	function test_genusers()
	{
		$add_sizes = array(
			10 => 'Few',
			30 => 'Some',
			100 => 'A lot of'
		);
		if (@$_POST['sub_cancel'])
		{
			@ header('Location: ?');
			exit();
		}
		
		if (@$_POST['sub_gen'])
		{
			if (@$_SESSION['captcha_keystring'] !== $_POST['captcha'])
			{
				$this->errors[] = 'Wrong captcha!';
			}
			
			$count = 50;
			if (isset($add_sizes[$_POST['count']]))
				$count = intval ($_POST['count']);
			
			if (!$this->errors)
			{
				$json_data = @ json_decode(file_get_contents('http://api.randomuser.me/?results='.$count), true);
				
				if ($json_data)
				{

				} else {
					$this->errors[] = 'JSON request error';
				}
				print_r($json_data);
			}
		}
		$fg = new lform();
		$fg->add_title('New users generator');
		
		# Load text description from template (tpl/form_intro.tpl)
		$fg->add_paragraph(ltpl::file('form_intro'));
		
		# Options
		$fg->add_title('Adding options');
		$fg->add_checkbox('Add boys', 'add_boys', true, 'Load <b style="color:navy">boys</b> records from generator');
		$fg->add_checkbox('Add girls', 'add_girls', true, 'Load <b style="color:red">girls</b> records from generator');
		$fg->add_select('Count', 'count', $add_sizes, 30);
		
		# Captcha block
		$fg->add_title('Security code');
		$fg->add_raw(ltpl::file('captcha'));
		
		# Submit block
		$fg->add_submit('Generate', 'sub_gen');
		$fg->add_submit('Cancel', 'sub_cancel');
		$fg->append_last(); # This will add 'Cancel' button in one row with 'Generate'
		
		return $fg->get_form();
	}
}

$site = new example_site();
echo $site->show();

?>