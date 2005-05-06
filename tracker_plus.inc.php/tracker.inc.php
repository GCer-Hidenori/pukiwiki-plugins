<?php
// PukiWiki - Yet another WikiWikiWeb clone
// $Id: tracker.inc.php,v 1.29 2005/03/02 13:31:05 henoheno Exp $
//
// Issue tracker plugin (See Also bugtrack plugin)
// This script is modified by jjyun. (2004/02/22 - 2005/02/08) 
//   tracker.inc.php-modified, v 1.6 2005/03/19 13:15:32 jjyun
//
// License   : PukiWiki ���Τ�Ʊ���� GNU General Public License (GPL) �Ǥ�

// tracker_list��ɽ�����ʤ��ڡ���̾(����ɽ����)
// 'SubMenu'�ڡ��� ����� '/'��ޤ�ڡ������������
define('TRACKER_LIST_EXCLUDE_PATTERN','#^SubMenu$|/#');
// ���¤��ʤ����Ϥ�����
//define('TRACKER_LIST_EXCLUDE_PATTERN','#(?!)#');

// ���ܤμ��Ф��˼��Ԥ����ڡ����������ɽ������
define('TRACKER_LIST_SHOW_ERROR_PAGE',TRUE);

// CacheLevel�Υǥե���Ȥ�����
// ** �����ͤ����� ** ����ͤϾ�Ĺ�⡼�ɤ�ɽ���ޤ�
//        0 : ����å�����å������Ѥ��ʤ� 
//  1 or -1 : �ڡ������ɤ߹��߽������Ф��륭��å����ͭ���ˤ���
//  2 or -2 : html���Ѵ���Υǡ����Υ���å����ͭ���ˤ���

define('TRACKER_LIST_CACHE_DEFAULT', 0); 
// define('TRACKER_LIST_CACHE_DEFAULT', 1); 
// define('TRACKER_LIST_CACHE_DEFAULT', 2); 

function plugin_tracker_convert()
{
	global $script,$vars;

	if (PKWK_READONLY) return ''; // Show nothing

	$base = $refer = $vars['page'];

	$config_name = 'default';
	$form = 'form';
	$options = array();

	global $html_transitional;
	$isDatefield = FALSE;

	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
			case 3:
				$options = array_splice($args,2);
			case 2:
				$args[1] = get_fullname($args[1],$base);
				$base = is_pagename($args[1]) ? $args[1] : $base;
			case 1:
				$config_name = ($args[0] != '') ? $args[0] : $config_name;
				list($config_name,$form) = array_pad(explode('/',$config_name,2),2,$form);
		}
	}

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		return "<p>config file '".htmlspecialchars($config_name)."' not found.</p>";
	}

	// Config���饹�ˤϡ�config_name ���������Ƥ��ʤ���(jjyun's comment)
	$config->config_name = $config_name;

	$fields = plugin_tracker_get_fields($base,$refer,$config);

	$form = $config->page.'/'.$form;
	if (!is_page($form))
	{
		return "<p>config file '".make_pagelink($form)."' not found.</p>";
	}
	$retval = convert_html(plugin_tracker_get_source($form));
	$hiddens = '';

	foreach (array_keys($fields) as $name)
	{
	        if (is_a($fields[$name],'Tracker_field_datefield')) {
			$isDatefield = TRUE;
		}

		$replace = $fields[$name]->get_tag();
		if (is_a($fields[$name],'Tracker_field_hidden'))
		{
			$hiddens .= $replace;
			$replace = '';
		}
		$retval = str_replace("[$name]",$replace,$retval);
	}

	if($isDatefield == TRUE)
	{
		Tracker_field_datefield::set_head_declaration();
		$number = plugin_tracker_getNumber();
		$form_scp = '<script type="text/javascript" src="' . SKIN_DIR . 'datefield.js"></script>';
		$form_scp .= <<<FORMSTR
<form enctype="multipart/form-data" action="$script" method="post" name="tracker$number" >
FORMSTR;
	}
	else
	{
		$form_scp = <<<FORMSTR
<form enctype="multipart/form-data" action="$script" method="post" >
FORMSTR;
	}

	return <<<EOD
$form_scp
<div>
$retval
$hiddens
</div>
</form>
EOD;
}

function plugin_tracker_getNumber() {
	global $vars;
	static $numbers = array();
	if (!array_key_exists($vars['page'],$numbers))
	{
		$numbers[$vars['page']] = 0;
	}
	return $numbers[$vars['page']]++;
}

function plugin_tracker_action()
{
	global $post, $vars, $now;

	if (PKWK_READONLY) die_message('PKWK_READONLY prohibits editing');

	$config_name = array_key_exists('_config',$post) ? $post['_config'] : '';

	$config = new Config('plugin/tracker/'.$config_name);
	if (!$config->read())
	{
		return "<p>config file '".htmlspecialchars($config_name)."' not found.</p>";
	}
	$config->config_name = $config_name;
	$source = $config->page.'/page';

	$refer = array_key_exists('_refer',$post) ? $post['_refer'] : $post['_base'];

	if (!is_pagename($refer))
	{
		return array(
			'msg'=>'cannot write',
			'body'=>'page name ('.htmlspecialchars($refer).') is not valid.'
		);
	}
	if (!is_page($source))
	{
		return array(
			'msg'=>'cannot write',
			'body'=>'page template ('.htmlspecialchars($source).') is not exist.'
		);
	}
	// �ڡ���̾�����
	$base = $post['_base'];
	$num = 0;
	$name = (array_key_exists('_name',$post)) ? $post['_name'] : '';
	if (array_key_exists('_page',$post))
	{
		$page = $real = $post['_page'];
	}
	else
	{
		$real = is_pagename($name) ? $name : ++$num;
		$page = get_fullname('./'.$real,$base);
	}
	if (!is_pagename($page))
	{
		$page = $base;
	}

	while (is_page($page))
	{
		$real = ++$num;
		$page = "$base/$real";
	}
	// �ڡ����ǡ���������
	$postdata = plugin_tracker_get_source($source);

	// ����Υǡ���
	$_post = array_merge($post,$_FILES);
	$_post['_date'] = $now;
	$_post['_page'] = $page;
	$_post['_name'] = $name;
	$_post['_real'] = $real;
	// $_post['_refer'] = $_post['refer'];

	$fields = plugin_tracker_get_fields($page,$refer,$config);

	// Creating an empty page, before attaching files
       	touch(get_filename($page));

	foreach (array_keys($fields) as $key)
	{
	        // modified for hidden2 by jjyun
		// $value = array_key_exists($key,$_post) ?
		// 	$fields[$key]->format_value($_post[$key]) : '';
	        $value = '';
		if( array_key_exists($key,$_post) ){
		  $value = is_a($fields[$key],"Tracker_field_hidden2") ?
		    $fields[$key]->format_value($_post[$key],$_post) :
		    $fields[$key]->format_value($_post[$key]);
		}

		foreach (array_keys($postdata) as $num)
		{
			if (trim($postdata[$num]) == '')
			{
				continue;
			}
			$postdata[$num] = str_replace(
				"[$key]",
				($postdata[$num]{0} == '|' or $postdata[$num]{0} == ':') ?
					str_replace('|','&#x7c;',$value) : $value,
				$postdata[$num]
			);
		}
	}

	// Writing page data, without touch
	page_write($page, join('', $postdata));

	$r_page = rawurlencode($page);

	pkwk_headers_sent();
	header('Location: ' . get_script_uri() . '?' . $r_page);
	exit;
}
/*
function plugin_tracker_inline()
{
	global $vars;

	if (PKWK_READONLY) return ''; // Show nothing

	$args = func_get_args();
	if (count($args) < 3)
	{
		return FALSE;
	}
	$body = array_pop($args);
	list($config_name,$field) = $args;

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		return "config file '".htmlspecialchars($config_name)."' not found.";
	}

	$config->config_name = $config_name;

	$fields = plugin_tracker_get_fields($vars['page'],$vars['page'],$config);
	$fields[$field]->default_value = $body;
	return $fields[$field]->get_tag();
}
*/
// �ե�����ɥ��֥������Ȥ��ۤ���
function plugin_tracker_get_fields($base,$refer,&$config)
{
	global $now,$_tracker_messages;

	$fields = array();
	// ͽ���
	foreach (array(
		'_date'=>'text',    // �������
		'_update'=>'date',  // �ǽ�����
		'_past'=>'past',    // �в�(passage)
		'_page'=>'page',    // �ڡ���̾
		'_name'=>'text',    // ���ꤵ�줿�ڡ���̾
		'_real'=>'real',    // �ºݤΥڡ���̾
		'_refer'=>'page',   // ���ȸ�(�ե�����Τ���ڡ���)
		'_base'=>'page',    // ���ڡ���
		'_submit'=>'submit' // �ɲåܥ���
		) as $field=>$class)
	{
		$class = 'Tracker_field_'.$class;
		$fields[$field] = &new $class(array($field,$_tracker_messages["btn$field"],'','20',''),$base,$refer,$config);
	}

	foreach ($config->get('fields') as $field)
	{
		// 0=>����̾ 1=>���Ф� 2=>���� 3=>���ץ���� 4=>�ǥե������
		$class = 'Tracker_field_'.$field[2];
		if (!class_exists($class))
		{ // �ǥե����
			$class = 'Tracker_field_text';
			$field[2] = 'text';
			$field[3] = '20';
		}
		$fields[$field[0]] = &new $class($field,$base,$refer,$config);
	}
	return $fields;
}
// �ե�����ɥ��饹
class Tracker_field
{
	var $name;
	var $title;
	var $values;
	var $default_value;
	var $page;
	var $refer;
	var $config;
	var $data;
	var $sort_type = SORT_REGULAR;
	var $id = 0;

	function Tracker_field($field,$page,$refer,&$config)
	{
		global $post;
		static $id = 0;

		$this->id = ++$id;
		$this->name = $field[0];
		$this->title = $field[1];
		$this->values = explode(',',$field[3]);
		$this->default_value = $field[4];
		$this->page = $page;
		$this->refer = $refer;
		$this->config = &$config;
		$this->data = array_key_exists($this->name,$post) ? $post[$this->name] : '';
	}
	function get_tag()
	{
	}
	function get_style($str)
	{
		return '%s';
	}
	function format_value($value)
	{
		return $value;
	}
	function format_cell($str)
	{
		return $str;
	}
	function get_value($value)
	{
		return $value;
	}
}
class Tracker_field_text extends Tracker_field
{
	var $sort_type = SORT_STRING;

	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$s_size = htmlspecialchars($this->values[0]);
		$s_value = htmlspecialchars($this->default_value);
		return "<input type=\"text\" name=\"$s_name\" size=\"$s_size\" value=\"$s_value\" />";
	}
}
class Tracker_field_page extends Tracker_field_text
{
	var $sort_type = SORT_STRING;

	function format_value($value)
	{
		global $WikiName;

		$value = strip_bracket($value);
		if (is_pagename($value))
		{
			$value = "[[$value]]";
		}
		return parent::format_value($value);
	}
}
class Tracker_field_real extends Tracker_field_text
{
	var $sort_type = SORT_REGULAR;
}
class Tracker_field_title extends Tracker_field_text
{
	var $sort_type = SORT_STRING;

	function format_cell($str)
	{
		make_heading($str);
		return $str;
	}
}
class Tracker_field_textarea extends Tracker_field
{
	var $sort_type = SORT_STRING;

	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$s_cols = htmlspecialchars($this->values[0]);
		$s_rows = htmlspecialchars($this->values[1]);
		$s_value = htmlspecialchars($this->default_value);
		return "<textarea name=\"$s_name\" cols=\"$s_cols\" rows=\"$s_rows\">$s_value</textarea>";
	}
	function format_cell($str)
	{
		$str = preg_replace('/[\r\n]+/','',$str);
		if (!empty($this->values[2]) and strlen($str) > ($this->values[2] + 3))
		{
			$str = mb_substr($str,0,$this->values[2]).'...';
		}
		return $str;
	}
}
class Tracker_field_format extends Tracker_field
{
	var $sort_type = SORT_STRING;

	var $styles = array();
	var $formats = array();

	function Tracker_field_format($field,$page,$refer,&$config)
	{
		parent::Tracker_field($field,$page,$refer,$config);

		foreach ($this->config->get($this->name) as $option)
		{
			list($key,$style,$format) = array_pad(array_map(create_function('$a','return trim($a);'),$option),3,'');
			if ($style != '')
			{
				$this->styles[$key] = $style;
			}
			if ($format != '')
			{
				$this->formats[$key] = $format;
			}
		}
	}
	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$s_size = htmlspecialchars($this->values[0]);
		return "<input type=\"text\" name=\"$s_name\" size=\"$s_size\" />";
	}
	function get_key($str)
	{
		return ($str == '') ? 'IS NULL' : 'IS NOT NULL';
	}
	function format_value($str)
	{
		if (is_array($str))
		{
			return join(', ',array_map(array($this,'format_value'),$str));
		}
		$key = $this->get_key($str);
		return array_key_exists($key,$this->formats) ? str_replace('%s',$str,$this->formats[$key]) : $str;
	}
	function get_style($str)
	{
		$key = $this->get_key($str);
		return array_key_exists($key,$this->styles) ? $this->styles[$key] : '%s';
	}
}
class Tracker_field_file extends Tracker_field_format
{
	var $sort_type = SORT_STRING;

	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$s_size = htmlspecialchars($this->values[0]);
		return "<input type=\"file\" name=\"$s_name\" size=\"$s_size\" />";
	}
	function format_value($str)
	{
		if (array_key_exists($this->name,$_FILES))
		{
			require_once(PLUGIN_DIR.'attach.inc.php');
			$result = attach_upload($_FILES[$this->name],$this->page);
			if ($result['result']) // ���åץ�������
			{
				return parent::format_value($this->page.'/'.$_FILES[$this->name]['name']);
			}
		}
		// �ե����뤬���ꤵ��Ƥ��ʤ��������åץ��ɤ˼���
		return parent::format_value('');
	}
}
class Tracker_field_radio extends Tracker_field_format
{
	var $sort_type = SORT_NUMERIC;

	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$retval = '';
		$id = 0;
		foreach ($this->config->get($this->name) as $option)
		{
			$s_option = htmlspecialchars($option[0]);
			$checked = trim($option[0]) == trim($this->default_value) ? ' checked="checked"' : '';
			++$id;
			$s_id = '_p_tracker_' . $s_name . '_' . $this->id . '_' . $id;
			$retval .= '<input type="radio" name="' .  $s_name . '" id="' . $s_id .
				'" value="' . $s_option . '"' . $checked . ' />' .
				'<label for="' . $s_id . '">' . $s_option . '</label>' . "\n";
		}

		return $retval;
	}
	function get_key($str)
	{
		return $str;
	}
	function get_value($value)
	{
		static $options = array();
		if (!array_key_exists($this->name,$options))
		{
			$options[$this->name] = array_flip(array_map(create_function('$arr','return $arr[0];'),$this->config->get($this->name)));
		}
		return array_key_exists($value,$options[$this->name]) ? $options[$this->name][$value] : $value;
	}
}
class Tracker_field_select extends Tracker_field_radio
{
	var $sort_type = SORT_NUMERIC;

	function get_tag($empty=FALSE)
	{
		$s_name = htmlspecialchars($this->name);
		$s_size = (array_key_exists(0,$this->values) and is_numeric($this->values[0])) ?
			' size="'.htmlspecialchars($this->values[0]).'"' : '';
		$s_multiple = (array_key_exists(1,$this->values) and strtolower($this->values[1]) == 'multiple') ?
			' multiple="multiple"' : '';
		$retval = "<select name=\"{$s_name}[]\"$s_size$s_multiple>\n";
		if ($empty)
		{
			$retval .= " <option value=\"\"></option>\n";
		}
		$defaults = array_flip(preg_split('/\s*,\s*/',$this->default_value,-1,PREG_SPLIT_NO_EMPTY));
		foreach ($this->config->get($this->name) as $option)
		{
			$s_option = htmlspecialchars($option[0]);
			$selected = array_key_exists(trim($option[0]),$defaults) ? ' selected="selected"' : '';
			$retval .= " <option value=\"$s_option\"$selected>$s_option</option>\n";
		}
		$retval .= "</select>";

		return $retval;
	}
}
class Tracker_field_checkbox extends Tracker_field_radio
{
	var $sort_type = SORT_NUMERIC;

	function get_tag($empty=FALSE)
	{
		$s_name = htmlspecialchars($this->name);
		$defaults = array_flip(preg_split('/\s*,\s*/',$this->default_value,-1,PREG_SPLIT_NO_EMPTY));
		$retval = '';
		$id = 0;
		foreach ($this->config->get($this->name) as $option)
		{
			$s_option = htmlspecialchars($option[0]);
			$checked = array_key_exists(trim($option[0]),$defaults) ?
				' checked="checked"' : '';
			++$id;
			$s_id = '_p_tracker_' . $s_name . '_' . $this->id . '_' . $id;
			$retval .= '<input type="checkbox" name="' . $s_name .
				'[]" id="' . $s_id . '" value="' . $s_option . '"' . $checked . ' />' .
				'<label for="' . $s_id . '">' . $s_option . '</label>' . "\n";
		}

		return $retval;
	}
}
class Tracker_field_hidden extends Tracker_field_radio
{
	var $sort_type = SORT_NUMERIC;

	function get_tag($empty=FALSE)
	{
		$s_name = htmlspecialchars($this->name);
		$s_default = htmlspecialchars($this->default_value);
		$retval = "<input type=\"hidden\" name=\"$s_name\" value=\"$s_default\" />\n";

		return $retval;
	}
}
class Tracker_field_submit extends Tracker_field
{
	function get_tag()
	{
		$s_title = htmlspecialchars($this->title);
		$s_page = htmlspecialchars($this->page);
		$s_refer = htmlspecialchars($this->refer);
		$s_config = htmlspecialchars($this->config->config_name);

		return <<<EOD
<input type="submit" value="$s_title" />
<input type="hidden" name="plugin" value="tracker" />
<input type="hidden" name="_refer" value="$s_refer" />
<input type="hidden" name="_base" value="$s_page" />
<input type="hidden" name="_config" value="$s_config" />
EOD;
	}
}
class Tracker_field_date extends Tracker_field
{
	var $sort_type = SORT_NUMERIC;

	function format_cell($timestamp)
	{
		return format_date($timestamp);
	}
}
class Tracker_field_past extends Tracker_field
{
	var $sort_type = SORT_NUMERIC;

	function format_cell($timestamp)
	{
		return get_passage($timestamp,FALSE);
	}
	function get_value($value)
	{
		return UTIME - $value;
	}
}
///////////////////////////////////////////////////////////////////////////
// ����ɽ��
function plugin_tracker_list_convert()
{
	global $vars;

	$config = 'default';
	$page = $refer = $vars['page'];
	$field = '_page';
	$order = '_real:SORT_DESC';
	$list = 'list';
	$limit = NULL;
	$filter = '';
	$cache = TRACKER_LIST_CACHE_DEFAULT;
	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
		        case 6:
			        $cache = is_numeric($args[5]) ? $args[5] : $cache;
		        case 5:
			        $filter = $args[4];
			case 4:
				$limit = is_numeric($args[3]) ? $args[3] : $limit;
			case 3:
				$order = $args[2];
			case 2:
				$args[1] = get_fullname($args[1],$page);
				$page = is_pagename($args[1]) ? $args[1] : $page;
			case 1:
				$config = ($args[0] != '') ? $args[0] : $config;
				list($config,$list) = array_pad(explode('/',$config,2),2,$list);
		}
	}
	return plugin_tracker_getlist($page,$refer,$config,$list,$order,$limit,$filter,$cache);
}
function plugin_tracker_list_action()
{
	global $script,$vars,$_tracker_messages;

	$page = $refer = $vars['refer'];
	$s_page = make_pagelink($page);
	$config = $vars['config'];
	$list = array_key_exists('list',$vars) ? $vars['list'] : 'list';
	$order = array_key_exists('order',$vars) ? $vars['order'] : '_real:SORT_DESC';
	$filter = array_key_exists('filter',$vars) ? $vars['filter'] : NULL;

	$cache = isset($vars['cache']) ? $vars['cache'] : NULL;

	// this delete tracker caches. 
	if( $cache == 'DELALL' )
	{
		if(! Tracker_list::delete_caches('(.*)(.tracker)$') )
		  die_message( CACHE_DIR . ' is not found or not readable.');

		return array(
			     'result' => FALSE,
			     'msg' => 'tracker_list caches are cleared.',
			     'body' =>'tracker_list caches are cleared.',
			     );
	}

	return array(
		     'msg' => $_tracker_messages['msg_list'],
		     'body'=> str_replace('$1',$s_page,$_tracker_messages['msg_back']).
		     plugin_tracker_getlist($page,$refer,$config,$list,$order,NULL,$filter,$cache)
	);
}
function plugin_tracker_getlist($page,$refer,$config_name,$list,$order='',$limit=NULL,$filter_name=NULL,$cache)
{
	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		return "<p>config file '".htmlspecialchars($config_name)."' is not exist.";
	}

	$config->config_name = $config_name;

	if (!is_page($config->page.'/'.$list))
	{
		return "<p>config file '".make_pagelink($config->page.'/'.$list)."' not found.</p>";
	}

	if($filter_name != NULL)
	{
	        $filter_config = new Config('plugin/tracker/'.$config->config_name.'/filters');
		if(!$filter_config->read())
		{
		        // filter�����꤬�ʤ���Ƥ��ʤ����, ���顼�����֤�
		        return "<p>config file '".htmlspecialchars($config->page.'/filters')."' not found</p>";
		}
	        $list_filter = &new Tracker_list_filter($filter_config, $filter_name);
	}
	unset($filter_config);

	// $list �ѿ����̤ΰ�̣�ǻȤ��ޤ蘆��Ƥ���Τ����!! (jjyun's comment)
	$list = &new Tracker_list($page,$refer,$config,$list,$filter_name,$cache);

	if($filter_name != NULL)
	{
		$list->rows = array_filter($list->rows, array($list_filter, 'filters') );
	}
	$list->sort($order);
	return $list->toString($limit);
}

// �������饹
class Tracker_list
{
	var $page;
	var $config;
	var $list;
	var $fields;
	var $pattern;
	var $pattern_fields;
	var $rows;
	var $order;
	var $filter_name;
	
	var $cache_level = array(
			   'NO'  => 0, // ����å�����å������Ѥ��ʤ�
			   'LV1' => 1, // �ڡ������ɤ߹��߽������Ф��륭��å����ͭ���ˤ���
			   'LV2' => 2, // html���Ѵ���Υǡ����Υ���å����ͭ���ˤ���
			   );

	var $cache = array('level' => TRACKER_LIST_CACHE_DEFAULT ,
			   'state' => array('stored_total' => 0,  // cache�ˤ���ǡ�����
					    'hits' => 0,          // cache��ˤ���ͭ���ʥǡ�����
					    'total' => 0,         // ����ʬ��ޤ�ƺǽ�Ū��ͭ���ʥǡ�����
					    'cnvrt' => FALSE), 
			   'verbs' => FALSE,
			   );

	function Tracker_list($page,$refer,&$config,$list,$filter_name,$cache)
	{
		$this->page = $page;
		$this->config = &$config;
		$this->list = $list;
		$this->filter_name = $filter_name;
		$this->fields = plugin_tracker_get_fields($page,$refer,$config);
		
		$pattern = join('',plugin_tracker_get_source($config->page.'/page'));
		// �֥�å��ץ饰�����ե�����ɤ��ִ�
		// #comment�ʤɤ������ʸ��������������ä����ˡ�[_block_xxx]�˵ۤ����ޤ���褦�ˤ���
		$pattern = preg_replace('/^\#([^\(\s]+)(?:\((.*)\))?\s*$/m','[_block_$1]',$pattern);

		// �ѥ����������
		$this->pattern = '';
		$this->pattern_fields = array();
		$pattern = preg_split('/\\\\\[(\w+)\\\\\]/',preg_quote($pattern,'/'),-1,PREG_SPLIT_DELIM_CAPTURE);
		while (count($pattern))
		{
			$this->pattern .= preg_replace('/\s+/','\\s*','(?>\\s*'.trim(array_shift($pattern)).'\\s*)');
			if (count($pattern))
			{
				$field = array_shift($pattern);
				$this->pattern_fields[] = $field;
				$this->pattern .= '(.*)';
			}
		}

		$this->cache['verbs'] = ($cache < 0) ? TRUE : FALSE;
		$this->cache['level'] = (abs($cache) <= $this->cache_level['LV2']) ? abs($cache) : $this->cache_level['NO']; 

                // �ڡ��������ȼ�����

		// cache ���� cache�������狼��ǡ����������
		// $this->add()�Ǥν��������ˤ��餫���� cache ���������Ǥ������Ȥǡ�
		// $this->add()��̵�¥롼���ɻߥ��å������Ѥ��ơ��оݥǡ�����ޤ�ڡ������ɤ߹��ߤ�Ԥ碌�ʤ���
                $this->get_cache_rows();

                $pattern = "$page/";
                $pattern_len = strlen($pattern);
                foreach (get_existpages() as $_page)
		{
			if (strpos($_page,$pattern) === 0)
			{
				$name = substr($_page,$pattern_len);
				if (preg_match(TRACKER_LIST_EXCLUDE_PATTERN,$name))
				{
					continue;
				}
				$this->add($_page,$name);
			}
		}
		$this->cache['state']['total'] = count($this->rows);
                $this->put_cache_rows();
        }
	function add($page,$name)
	{
		static $moved = array();

		// ̵�¥롼���ɻ�
		if (array_key_exists($name,$this->rows))
		{
			return;
		}

		$source = plugin_tracker_get_source($page);
		if (preg_match('/move\sto\s(.+)/',$source[0],$matches))
		{
			$page = strip_bracket(trim($matches[1]));
			if (array_key_exists($page,$moved) or !is_page($page))
			{
				return;
			}
			$moved[$page] = TRUE;
			return $this->add($page,$name);
		}
		$source = join('',preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/','$1$2',$source));

		// �ǥե������
		$this->rows[$name] = array(
			'_page'  => "[[$page]]",
			'_refer' => $this->page,
			'_real'  => $name,
			'_update'=> get_filetime($page),
			'_past'  => get_filetime($page),
		);
		if ($this->rows[$name]['_match'] = preg_match("/{$this->pattern}/s",$source,$matches))
		{
			array_shift($matches);
			foreach ($this->pattern_fields as $key=>$field)
			{
				$this->rows[$name][$field] = trim($matches[$key]);
			}
		}
	}
	function sort($order)
	{
		if ($order == '')
		{
			return;
		}
		$names = array_flip(array_keys($this->fields));
		$this->order = array();
		foreach (explode(';',$order) as $item)
		{
			list($key,$dir) = array_pad(explode(':',$item),1,'ASC');
			if (!array_key_exists($key,$names))
			{
				continue;
			}
			switch (strtoupper($dir))
			{
				case 'SORT_ASC':
				case 'ASC':
				case SORT_ASC:
					$dir = SORT_ASC;
					break;
				case 'SORT_DESC':
				case 'DESC':
				case SORT_DESC:
					$dir = SORT_DESC;
					break;
				default:
					continue;
			}
			$this->order[$key] = $dir;
		}
		$keys = array();
		$params = array();
		foreach ($this->order as $field=>$order)
		{
			if (!array_key_exists($field,$names))
			{
				continue;
			}
			foreach ($this->rows as $row)
			{
				$keys[$field][] = $this->fields[$field]->get_value($row[$field]);
			}
			$params[] = $keys[$field];
			$params[] = $this->fields[$field]->sort_type;
			$params[] = $order;

		}
		$params[] = &$this->rows;

		call_user_func_array('array_multisort',$params);
	}
	function replace_item($arr)
	{
		$params = explode(',',$arr[1]);
		$name = array_shift($params);
		if ($name == '')
		{
			$str = '';
		}
		else if (array_key_exists($name,$this->items))
		{
			$str = $this->items[$name];
			if (array_key_exists($name,$this->fields)) 
			{
				$str = $this->fields[$name]->format_cell($str);
			}
		}
		else
		{
			return $this->pipe ? str_replace('|','&#x7c;',$arr[0]) : $arr[0];
		}
		$style = count($params) ? $params[0] : $name;
		if (array_key_exists($style,$this->items)
			and array_key_exists($style,$this->fields))
		{
			$str = sprintf($this->fields[$style]->get_style($this->items[$style]),$str);
		}
		return $this->pipe ? str_replace('|','&#x7c;',$str) : $str;
	}
	function replace_title($arr)
	{
		global $script;

		$field = $sort = $arr[1];
		if ($sort == '_name' or $sort == '_page')
		{
			$sort = '_real';
		}
		if (!array_key_exists($field,$this->fields))
		{
			return $arr[0];
		}
		$dir = SORT_ASC;
		$arrow = '';
		$order = $this->order;

		if (is_array($order) && isset($order[$sort]))
		{
			$index = array_flip(array_keys($order));
			$pos = 1 + $index[$sort];
			$b_end = ($sort == array_shift(array_keys($order)));
			$b_order = ($order[$sort] == SORT_ASC);
			$dir = ($b_end xor $b_order) ? SORT_ASC : SORT_DESC;
			$arrow = '&br;'.($b_order ? '&uarr;' : '&darr;')."($pos)";
			unset($order[$sort]);
		}
		$title = $this->fields[$field]->title;
		$r_page = rawurlencode($this->page);
		$r_config = rawurlencode($this->config->config_name);
		$r_list = rawurlencode($this->list);
		$_order = array("$sort:$dir");
		if (is_array($order))
			foreach ($order as $key=>$value)
				$_order[] = "$key:$value";
		$r_order = rawurlencode(join(';',$_order));
		$r_filter = rawurlencode($this->filter_name);
		return "[[$title$arrow>$script?plugin=tracker_list&refer=$r_page&config=$r_config&list=$r_list&order=$r_order&filter=$r_filter]]";
	}
	function toString($limit=NULL)
	{
		global $_tracker_messages;

		$source = '';
		$body = array();

		if ($limit !== NULL and count($this->rows) > $limit)
		{
			$source = str_replace(
				array('$1','$2'),
				array(count($this->rows),$limit),
				$_tracker_messages['msg_limit'])."\n";
			$this->rows = array_splice($this->rows,0,$limit);
		}
		if (count($this->rows) == 0)
		{
			return '';
		}

		$htmls = $this->get_cache_cnvrt();
		if( strlen($htmls) > 0 )
		{
			return $htmls;
		}

		// This case is cache flag or status is not valie.
		foreach (plugin_tracker_get_source($this->config->page.'/'.$this->list) as $line)
		{
			if (preg_match('/^\|(.+)\|[hHfFcC]$/',$line))
			{
				$source .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'replace_title'),$line);
			}
			else
			{
				$body[] = $line;
			}
		}

		$lineno = 1;
		foreach ($this->rows as $key=>$row)
		{
			if (!TRACKER_LIST_SHOW_ERROR_PAGE and !$row['_match'])
			{
				continue;
			}

			$row['_line'] = $lineno++;  
			$this->items = $row;
			foreach ($body as $line)
			{
				if (trim($line) == '')
				{
					$source .= $line;
					continue;
				}
				$this->pipe = ($line{0} == '|' or $line{0} == ':');

				$source .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'replace_item'),$line);
			}
		}

		$htmls = convert_html($source);
		$this->put_cache_cnvrt($htmls);

		if($this->cache['verbs'] == TRUE) 
		{
			$htmls .= $this->get_verbose_cachestatus();
		}

		return $htmls;
	}

	function get_cache_filename()
	{
		$r_page   = encode($this->page);
                $r_config = encode($this->config->config_name);
                $r_list   = encode($this->list);
		return "$r_page-$r_config-$r_list";
	}		
	function get_listcache_filename()
	{
		return CACHE_DIR . $this->get_cache_filename().".1.tracker";
	}		
	function get_cnvtcache_filename()
	{
                $r_filter   = encode($this->filter_name);
		return CACHE_DIR . $this->get_cache_filename()."-$r_filter.2.tracker";
	}
 
	function get_verbose_cachestatus()
	{
		if( $this->cache['level'] == $this->cache_level['NO'] )
		{
			return '';  
		} 
		else
		{
			$status = '<div style="text-align: right; font-size: x-small;" > '
			  . "cache level = {$this->cache['level']}, "
			  . "Level1.cache hit rate = "
			  . "{$this->cache['state']['hits']}/{$this->cache['state']['total']} "
			  . "Level2.cache is = ";

			if( $this->cache['state']['cnvrt'] )
			{
			  $status .= 'Valid';
			}
			else
			{
			  $status .= ( $this->cache['level'] == $this->cache_level['LV2'] )  ? 'NotValid': 'NotEffective';
			}
		}
		$status .= '</div>';
		return $status;
	}
	function get_cache_rows()
	{
		$this->rows = array();
		$cachefile = $this->get_listcache_filename();
		if (! file_exists($cachefile) )
		{
			return;
		}
		// This confirm whether config files were changed or not. 
		$cache_time = filemtime($cachefile) - LOCALZONE;
		if( ( get_filetime($this->config->page) > $cache_time) 
		    or ( get_filetime($this->config->page . '/' .  $this->list) > $cache_time ) )
		{
			return ;
		}


		$fp = fopen($cachefile,'r')
		  or die('cannot open '.$cachefile);

		set_file_buffer($fp, 0);
		flock($fp,LOCK_EX);
		rewind($fp);

		// This will get us the main column names.
		// (jjyun) I tryed csv_explode() function , but this behavior is not match as I wanted.

		$column_names = fgetcsv($fp, filesize($cachefile));

		$stored_total = 0;
		while ($arr = fgetcsv($fp, filesize($cachefile)) )
		{
		  
			$row = array();
			$stored_total +=1;
			// This code include cache contents in $rows.
			foreach($arr as $key => $value)
			{
				$column_name = $column_names[$key];
				// (note) '_match' is not fields ,
				//  but '_match' value is effect for tracker_list.
				if( isset($this->fields[$column_name]) || $column_name =='_match')
				{
					$row[$column_name] = stripslashes($value);
				}
			}

			// This code check cache effective.
			//  by means of comparing filetime between real page and cache contents.
			// If cache is effective, this code include cache contents in rows.

			if ( isset($row['_real'])
			     and isset($row['_update']) 
			     and (get_filetime($this->page.'/'.$row['_real']) == $row['_update']) )
			{
				$this->rows[$row['_real']] = $row;
			}
		}

		flock($fp,LOCK_UN);
		fclose($fp);

		$this->cache['state']['stored_total'] = $stored_total;
		$this->cache['state']['hits'] = count($this->rows);
	}

	function put_cache_rows()
	{
		$cachefiles_pattern = '^' . $this->get_cache_filename() . '(.*).tracker$';

		if( $this->cache['level'] == $this->cache_level['NO'] )
		{
			if(! $this->delete_caches($cachefiles_pattern) )
			  die_message( CACHE_DIR . ' is not found or not readable.');
			return;
		}
		if( $this->cache['state']['hits'] == $this->cache['state']['total'] 
		   and $this->cache['state']['hits'] == $this->cache['state']['stored_total'] )
		{  
			return '';
		}

		// This delete cachefiles related this Lv1.cache.
		if(! $this->delete_caches($cachefiles_pattern) )
		  die_message( CACHE_DIR . ' is not found or not readable.');

		ksort($this->rows);
		$filename = $this->get_listcache_filename();
		
		$fp = fopen($filename, 'w')
			or die('cannot open '.$filename);

		set_file_buffer($fp, 0);
		if(! flock($fp, LOCK_EX) )
		{
			return FALSE;  
		}

		$column_names = array();
                foreach (plugin_tracker_get_source($this->config->page.'/'.$this->list) as $line)
		{
			if (! preg_match('/^\|(.+)\|[hHfFcC]$/',$line))
			{
				// It convert '|' for table separation to ',' for CSV format separation.
				preg_match_all('/\[([^\[\]]+)\]/',$line,$item_array);
				foreach ($item_array[1] as $item)
				{
					$params = explode(',',$item);
					$name = array_shift($params);
					if($name != '')
						array_push($column_names,"$name");
				}
			}
		}
                // add default parameter
                $column_names = array_merge($column_names,
					    array('_page','_refer','_real','_update','_match'));
                $column_names = array_unique($column_names);

		fputs($fp, "\"" . implode('","', $column_names)."\"\n");

		foreach ($this->rows as $row)
		{
			$arr = array();
			foreach ( $column_names as $key)
			{
	 			$arr[$key] = addslashes($row[$key]);
			}
		fputs($fp, "\"" . implode('","', $arr) . "\"\n");
		}
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	function get_cache_cnvrt()
	{
		$cachefile = $this->get_cnvtcache_filename(); 
		if(! file_exists($cachefile) ) 
		{  
			return '';
		}
		if( $this->cache['level'] != $this->cache_level['LV2'] )
		{
			unlink($cachefile);  
			return '';
		}
		if($this->cache['state']['hits'] != $this->cache['state']['total'] or 
		   $this->cache['state']['hits'] != $this->cache['state']['stored_total'] ) 
		{  
			return '';
		}
		// This confirm whether config files were changed or not. 
		$cache_time = filemtime($cachefile) - LOCALZONE;
		if( ( get_filetime($this->config->page) > $cache_time) 
		    or ( get_filetime($this->config->page . $this->list) > $cache_time ) 
		    or ( is_page($this->config->page . $this->filter_name)
			 and get_filetime($this->config->page . $this->filter_name) > $cache_time ) )
		{
			unlink($cachefile);  
			return '';
		}

		if( function_exists('file_get_contents' ) ) 
		{
			// file_get_contents is for PHP4 > 4.3.0, PHP5 function  
			$htmls = file_get_contents($cachefile); 
		}
		else 
		{
			$fp = fopen($cachefile,'r')
			  or die('cannot open '.$cachefile);
			$htmls = "";
			do
			{
				$data = fread($fp, 8192);
				if (strlen($data) == 0) {
					break;
				}
				$htmls .= $data;
			} while(true);
		}
		$this->cache['state']['cnvrt'] = TRUE;

		if( $this->cache['verbs'] == TRUE ) 
		{
			$htmls .= $this->get_verbose_cachestatus();
		}

		return $htmls;
	}
	function put_cache_cnvrt($htmls)
	{
		if( $this->cache['level'] != $this->cache_level['LV2'] )
		{
			return ;
		}

		// toString() �η�̤򥭥�å���Ȥ��ƽ񤭽Ф�
		$cachefile = $this->get_cnvtcache_filename(); 

		$fp = fopen($cachefile, 'w')
			or die('cannot open '.$cachefile);

		set_file_buffer($fp, 0);
		flock($fp, LOCK_EX);
		fwrite($fp, $htmls);
		flock($fp,LOCK_UN);
		fclose($fp);
	}

	// static method.
	function delete_caches($del_pattern)
	{
	        $dir = CACHE_DIR;
		if(! $dp = @opendir($dir) )
		{
			return FALSE;
		}
		while($file = readdir($dp))
		{
			if(preg_match("/$del_pattern/",$file))
			{
				unlink($dir . $file);
			}
		}
		closedir($dp);
		return TRUE;
	}
}

function plugin_tracker_get_source($page)
{
	$source = get_source($page);
	// ���Ф��θ�ͭID������
	$source = preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/m','$1$2',$source);
	// #freeze����
	return preg_replace('/^#freeze\s*$/im', '', $source);

}

// I want to make Tracker_list_filter and Tracker_list_filterCondition to
// inner class of Tracker_list. But inner class is supported by PHP5, not PHP4.(jjyun)
class Tracker_list_filter
{
	var $filter_name;
	var $filter_conditions = array();
  
	function Tracker_list_filter($filter_config, $filter_name)
	{
		$this->filter_name = $filter_name;
		foreach( $filter_config->get($filter_name) as $filter )
		{
			array_push( $this->filter_conditions,
				    new Tracker_list_filterCondition($filter, $filter_name) );
		}
	}

	function filters($var)
	{
		$counter = 0;  
		$condition_flag = true;
		foreach($this->filter_conditions as $filter)
		{
			if($filter->is_cnctlogic_AND or $counter == 0)
			{
				$condition_flag = ($filter->filter($var) and $condition_flag );
			}
			else
			{  
				$condition_flag = ($filter->filter($var)  or $condition_flag );
			}
			$counter++ ;
		}
		return $condition_flag;
	}
}
class Tracker_list_filterCondition
{
	var $name;
	var $target;
	var $matches;
	var $is_exclued;
	var $is_cnctlogic_AND;
  
	function Tracker_list_filterCondition($field,$name)
	{
		$this->name = $name;
		$this->is_cnctlogic_AND = ($field[0] == "����") ? true : false ;
		$this->target = $field[1];
		$this->matches = preg_quote($field[2],'/');
		$this->matches = implode(explode(',',$this->matches) ,'|');
		$this->is_exclued = ($field[3] == "����") ? true : false ;
		
	}
  
	function filter($var)
	{
		$flag = preg_match("/$this->matches/",$var[$this->target]);
		return ($this->is_exclued) ? (! $flag): $flag;
	}

	function toString()
	{
		$str =
		  "name   : $this->name |"
		  . "target : $this->target |"
		  . "matches: $this->matches |"
		  . "exc-lgc: $this->is_exclued | "
		  . "cnctlgc: $this->is_cnctlogic_AND |";
		return $str;
	}
}
class Tracker_field_select2 extends Tracker_field_select
{
	var $sort_type = SORT_NUMERIC;
  

	//Tracker_field_select �ˤ���multiple ���꤬�Ǥ��ʤ��褦�ˤ��롣
	function get_tag($empty=FALSE)
	{
		$s_name = htmlspecialchars($this->name);
		$retval = "<select name=\"{$s_name}[]\">\n";
		if ($empty)
		{
			$retval .= " <option value=\"\"></option>\n";
		}
		$defaults = array_flip( preg_split('/\s*,\s*/',$this->default_value,-1,PREG_SPLIT_NO_EMPTY));

		foreach ($this->config->get($this->name) as $option)
		{
			$s_option = htmlspecialchars($option[0]);
			$selected = array_key_exists(trim($option[0]),$defaults) ? ' selected="selected"' : '';
			$retval .= " <option value=\"$s_option\"$selected>$s_option</option>\n";
		}
		$retval .= "</select>";
    
		return $retval;
	}
  
	// (sort��Ŭ�ѻ�������)
	// ����(page��γ�����ʬ)��config�ڡ�����°���Ͱ���������������Ǥ��ޤޤ��С�
	// °���Ͱ�����������줿�����Ф����ͤ��֤�
	function get_value($value)
	{
		// config �ڡ�����°���ͤ��ɤ߼�ꡢ
		// ����°���ͤ��Ф��ƻ����˾���˿��򿶤ä�������������
		static $options = array();
		if (!array_key_exists($this->name,$options))
		{ 
			$options[$this->name] = array_flip(array_map(create_function('$arr','return $arr[0];'), $this->config->get($this->name)));
		}

		$regmatch_value=$this->get_key($value);

		// �����ͤ� config �ڡ����ǻ��ꤵ�줿�ͤǤ���С�
		// �嵭�ǵ�᤿�����򼨤��ͤ��֤�
		if( array_key_exists($regmatch_value,$options[$this->name]) ) 
		{
			return $options[$this->name][$regmatch_value];
		}
		else 
		{
		  return $regmatch_value;
		}
	}
  
	// (style��Ŭ�ѻ���listɽ�����Ƥˡ����Ѥ����)
	// ����(page��γ�����ʬ)��config�ڡ�����°���Ͱ���������������Ǥ��ޤޤ��С�
	// ���θ��Ф����ͤ��֤�
	function get_key($str)
	{
		// �����ե�����ɤ�BlockPlugin��٤�ʸ���󤫤�0���ܤΰ����ˤ�����ʸ������ɤ߼��
		$arg= Tracker_field_string_utility::get_argument_from_block_type_plugin_string($str);

		// config�ڡ��������ꤵ�줿°���ͤ���Ӥ���
		foreach ($this->config->get($this->name) as $option) {
			// '/'ʸ�����������ʸ��������äƤ�����Ǥ���褦��escape����
		 	$eoption=preg_quote($option[0],'/');
			if(preg_match("/^$eoption$/",$arg)){
			  return $option[0];
			}
		}
		return $arg;
	}
  
	// ����(page��γ�����ʬ)��config�ڡ�����°���Ͱ���������������Ǥ��ޤޤ��С�
	// ���θ��Ф����ͤ��֤�(tracker_listɽ���ǡ����Ѥ���Ƥ���)
	function format_cell($str)
	{
		return $this->get_key($str);
	}
}
class Tracker_field_hidden2 extends Tracker_field_hidden
{
	var $sort_type = SORT_REGULAR;
  
	// (sort��Ŭ�ѻ��ˡ����Ѥ���Ƥ���)
	// ����(page��γ�����ʬ)���Ф��ơ�config�ڡ����Υ��ץ�������˽��äơ�
	// �֥�å����Υץ饰�������������ꤵ�줿��ʬ��ʸ�����
	// �ڤ�Ф����ͤ��֤�������ޤ�
	function get_value($value)
	{
		$extract_arg_num = (array_key_exists(0,$this->values) and is_numeric($this->values[0])) ?
		  htmlspecialchars($this->values[0]) : '' ;
		$target_plugin_name = array_key_exists(1,$this->values) ?
		  htmlspecialchars($this->values[1]) : '.*' ;
		$target_plugin_type = array_key_exists(2,$this->values) ?
		  htmlspecialchars($this->values[2]) : 'block' ;
    
		// ���ץ����λ��꤬�ʤ���С���ĥ�����ϹԤ�ʤ�
		if($extract_arg_num == '')
		{
		  return $value;
		}

		// ���ꤵ�줿�ץ饰���󤫤���֤ΰ�������Ф���
		$arg = Tracker_field_string_Utility::get_argument_from_plugin_string(
			    $value, $extract_arg_num, $target_plugin_name, $target_plugin_type);

		// ��Ф������֤ΰ������Ф��ơ����������ɽ���ˤ����л��꤬����Ф����Ԥ�
		if( $expatern_with_argument != null && 
		    preg_match("/$expatern_with_argument/",$arg,$match) )
		{
			$arg = $match[1];
		}

		return $arg;
	}
	// ����(page��γ�����ʬ)���Ф��ơ�
	// config�ڡ����Υ��ץ�������˽��ä��ڤ�Ф����ͤ��֤���
	// page��γ�����ʬ��config�ڡ�����°���Ͱ���������������Ǥ��ޤޤ��С�
	// ���θ��Ф����ͤ��֤���style��Ŭ�ѻ��ˡ����Ѥ���Ƥ����
	function get_key($str)
	{
		// ����(page��γ�����ʬ)���Ф��ơ�config�ڡ����Υ��ץ�������˽��ä��ڤ�Ф���
		$str= $this->get_value($str);
		foreach ($this->config->get($this->name) as $option)
		{
			// '/'ʸ�����������ʸ��������äƤ�����Ǥ���褦��escape����
			$eoption=preg_quote($option[0],'/');
			if( preg_match("/$eoption/",$str) )
			{
				return $option[0];
			}
		}
		return $str;
	}
  
	// ����(page��γ�����ʬ)���Ф��ơ�config�ڡ����Υ��ץ�������˽��ä��ڤ�Ф����ͤ��֤���
	// page��γ�����ʬ��config�ڡ�����°���Ͱ���������������Ǥ��ޤޤ��С�
	// ���θ��Ф����ͤ��֤�(tracker_listɽ���ǡ����Ѥ���Ƥ���)
	function format_cell($str)
	{
		return $this->get_value($str);
	}
	// Page��ž������ݤ��ͤ��֤�
	function format_value($value,$post)
	{
		$str=$value;
		
		foreach( array_keys($post) as $postkey )
		{
			if( preg_match("[$postkey]",$str) )
			{
				// �ִ����䤬 Array�ˤʤ���ϡ����󤫤���Ƭ���Ǥ������Ƥ�
				if( is_array($post[$postkey]) )
				{
				  $str = str_replace("[$postkey]",array_shift($post[$postkey]),$str);
				}
				else
				{
				  $str = str_replace("[$postkey]",$post[$postkey],$str);
				}
			}
		}
		return parent::format_value($str);
	}
}
class Tracker_field_hidden3 extends Tracker_field_hidden2
{
	var $sort_type = SORT_NUMERIC;
	// (sort��Ŭ�ѻ��ˡ����Ѥ���Ƥ���)
	// ����(page��γ�����ʬ)���Ф��ơ�config�ڡ����Υ��ץ�������˽��äơ�
	// �֥�å����Υץ饰�������������ꤵ�줿��ʬ��ʸ�����
	// �ڤ�Ф����ͤ��֤�������ޤ�
	function get_value($value)
	{
		$extract_arg_num = (array_key_exists(0,$this->values) and is_numeric($this->values[0])) ?
		  htmlspecialchars($this->values[0]) : '' ;
		$target_plugin_name = array_key_exists(1,$this->values) ?
		  htmlspecialchars($this->values[1]) : '.*' ;
		$target_plugin_type = array_key_exists(2,$this->values) ?
		  htmlspecialchars($this->values[2]) : 'block' ;
    
		// ���ץ����λ��꤬�ʤ���С���ĥ�����ϹԤ�ʤ�
		if($extract_arg_num == '')
		{
		  return $value;
		}

		// ���ꤵ�줿�ץ饰���󤫤���֤ΰ�������Ф���
		$arg = Tracker_field_string_Utility::get_argument_from_plugin_string(
			    $value, $extract_arg_num, $target_plugin_name, $target_plugin_type);

		// ��Ф������֤ΰ������Ф��ơ����������ɽ���ˤ����л��꤬����Ф����Ԥ�
		$arg = (preg_match("/(\d+)/",$arg,$match) ) ? $match[1] : 0;

		return $arg;
	}
}

class Tracker_field_datefield extends Tracker_field
{
	function get_tag()
	{
    		$s_name = htmlspecialchars($this->name);
		$s_size = (array_key_exists(0,$this->values)) ? htmlspecialchars($this->values[0]) : '10';
		$s_format = (array_key_exists(1,$this->values)) ? htmlspecialchars($this->values[1]) : 'YYYY-MM-DD';
		$s_value = htmlspecialchars($this->default_value);
		
		$s_year  = date("Y",time());
		$s_month = date("m",time()); 
		$s_date  = date("d",time()); 
		
		require_once( PLUGIN_DIR . 'datefield.inc.php');
		// �ǥե�����ͤ򸽺ߤ����դˤ���
		if($s_value=="NOW")
		{
		  $s_value = Tracker_field_datefield::get_datestr_with_format($s_format, $s_year, $s_month-1, $s_date);
		}
		// Javascript�˰����錄�������Υե����ޥå�ʸ������ѹ�����
		$s_format = Tracker_field_datefield::form_format($s_format);
		
		return <<<EOD
<input type="text" name="$s_name" size="$s_size" value="$s_value" />
<input type="button" value="..." onclick="dspCalendar(this.form.$s_name, event, $s_format, 0 , $s_year, $s_month-1, $s_date, 0);" />
EOD;
	}
  
	// sort��Ŭ�ѻ��ˡ������ͤ�ʤäƽ�����Ԥ碌�롣
	// ������ʬ�˴ޤޤ��֥�å��ץ饰�����0���ܤΰ������֤�
	function get_value($value)
	{
		$arg= Tracker_field_string_utility::get_argument_from_block_type_plugin_string($value,0,'datefield');
		return $arg;
	}

	// tracker_listɽ���ǤΡ��������Ƥ��֤�
	function format_cell($str)
	{
		return $this->get_value($str);
	}
  
	// Page��ž������ݤ��ͤ��֤�
	function format_value($value)
	{
		$s_format = (array_key_exists(1,$this->values)) ? htmlspecialchars($this->values[1]) : 'YYYY-MM-DD';
		$s_unmdfy = (array_key_exists(2,$this->values)) ? htmlspecialchars($this->values[2]) : 'FALSE';
    		if($s_unmdfy != 'TRUE')
		{
		  $value = "#datefield($value,$s_format)";
		}
		return parent::format_value($value);
	}

	function form_format($format_opt) {
		$format_str= trim($format_opt);
		if(strlen($format_str) == 0 )  $format_str = 'YYYY/MM/DD';
		if(preg_match('/^[\'\"].*[\"\']$/',$format_str)) /* " */
		{ 
			$format_str = '\'' . substr($format_str,1,strlen($format_str)-2) . '\'';
		}
		else
		{
			$format_str = '\'' . $format_str . '\'';
		}
		return $format_str;
	}

	// �������ͤϴ���2����ͤȤ����Ϥ�����
	function get_datestr_with_format($format_opt,$yyyy,$mm,$dd ){
		$strWithFormat = $format_opt;
		$yy = $yyyy%100;
		
		$mm += 1; // �����η���ͤ��ϰ� month is 0 - 11
		$strWithFormat = preg_replace('/YYYY/i', $yyyy, $strWithFormat);
		$strWithFormat = preg_replace('/YY/i',   $yy,   $strWithFormat);
		$strWithFormat = preg_replace('/MM/i',   $mm,   $strWithFormat);
		$strWithFormat = preg_replace('/DD/i',   $dd,   $strWithFormat);

		return $strWithFormat;
	}

	function set_head_declaration() {
		global $html_transitional, $javascript;

		// XHTML 1.0 Transitional
		$html_transitional = TRUE;
		
		// <head> ������ؤ� <meta>������ɲ�
		$javascript = TRUE;
	}
}

class Tracker_field_string_utility {
  
  	function get_argument_from_block_type_plugin_string($str,
						      $extract_arg_num = 0,
						      $plugin_name = '.*' ) {
	  return Tracker_field_string_utility::get_argument_from_plugin_string($str,$extract_arg_num,$plugin_name,'block');
	}
  
	// plugin_type : block type = 0, inline type = 1.
	// extract_arg_num : first argument number is 0.  
	function get_argument_from_plugin_string($str, 
						 $extract_arg_num, $plugin_name, $plugin_type = 'block')
	{
		$str_plugin = ($plugin_type == 'inline') ? '\&' : '\#' ;
		$str_plugin .= $plugin_name;
		
		$matches = array();

		// ʣ����plugin���꤬¸�ߤ�����Ǥ����Ƥ��Ф�����Ф�Ԥ�
		if( preg_match_all("/(?:$str_plugin\(([^\)]*)\))/", $str, $matches, PREG_SET_ORDER) )
		{
			$paddata = preg_split("/$str_plugin\([^\)]*\)/", $str);
			$str = $paddata[0];
                        foreach($matches as $i => $match)
			{
				$args = array();

				$str_arg = $match[1];
				$args = explode("," , $str_arg);
				if( is_numeric($extract_arg_num) && $extract_arg_num < count($args) )
				{
					$extract_arg = $args[ $extract_arg_num ];
				}
				else
				{
		  			$extract_arg = $str_plugin . $str_arg;
				}
			}
			// block-type,inline-type �Υץ饰�������ˤ����ơ�
			// �Ǹ�γ�̤θ�˥��ߥ���󤬤�����Ȥʤ���礬¸�ߤ��뤿�ᡢ
			// ���ߥ����ľ��ˤ��ä����ϡ��ץ饰�������ΰ�����ª���ƽ����Ԥ�
			if( preg_match("/^\;.*$/",$paddata[$i+1],$exrep) )
			{
				$paddata[$i+1] = $exrep[1];
			}
                        $str .= $extract_arg . $paddata[$i+1];
                }
		return $str;
	}
}
?>
