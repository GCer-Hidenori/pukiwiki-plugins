<?php
// PukiWiki - Yet another WikiWikiWeb clone
// $Id: tracker_plus.inc.php,v 2.7 2005/11/30 01:55:35 jjyun Exp $
// Copyright (C) 
//   2004-2005 written by jjyun ( http://www2.g-com.ne.jp/~jjyun/twilight-breeze/pukiwiki.php )
// License: GPL v2 or (at your option) any later version
//
// Issue from tracker.inc.php plugin (See Also bugtrack plugin)

require_once(PLUGIN_DIR . 'tracker.inc.php');

/////////////////////////////////////////////////////////////////////////////
// tracker_list��ɽ�����ʤ��ڡ���̾(����ɽ����)
// 'SubMenu'�ڡ��� ����� '/'��ޤ�ڡ������������
define('TRACKER_PLUS_LIST_EXCLUDE_PATTERN','#^SubMenu$|/#');
// ���¤��ʤ����Ϥ�����
//define('TRACKER_PLUS_LIST_EXCLUDE_PATTERN','#(?!)#');

/////////////////////////////////////////////////////////////////////////////
// ���ܤμ��Ф��˼��Ԥ����ڡ����������ɽ������
define('TRACKER_PLUS_LIST_SHOW_ERROR_PAGE',TRUE);


/////////////////////////////////////////////////////////////////////////////
// CacheLevel�Υǥե���Ȥ�����
// ** �����ͤ����� ** ����ͤϾ�Ĺ�⡼�ɤ�ɽ���ޤ�
//        0 : ����å�����å������Ѥ��ʤ� 
//  1 or -1 : �ڡ������ɤ߹��߽������Ф��륭��å����ͭ���ˤ���
//  2 or -2 : html���Ѵ���Υǡ����Υ���å����ͭ���ˤ���
define('TRACKER_PLUS_LIST_CACHE_DEFAULT', 0); 
// define('TRACKER_PLUS_LIST_CACHE_DEFAULT', 1); 
// define('TRACKER_PLUS_LIST_CACHE_DEFAULT', 2); 

/////////////////////////////////////////////////////////////////////////////
// �ե��륿�������ˤ����롢ưŪ�ե��륿����Υǥե��������
//  ** �����ͤ����� ** filter̾��Ƭ�� + or - ���դ���ȥꥹ��ɽ��������ϲ�ǽ�Ǥ���...
//   TRUE : filter̾����Ƭ�� + ����ꤷ�ʤ��Ƥ�ե��륿�����ѤΥꥹ�Ȥ�ɽ�����ޤ�
//  FALSE : filter̾����Ƭ�� + ����ꤷ�ʤ��ȡ��ե��륿�����ѤΥꥹ�Ȥ�ɽ�����ޤ���
define('TRACKER_PLUS_LIST_DYNAMIC_FILTER_DEFAULT', TRUE);

/////////////////////////////////////////////////////////////////////////////
// ưŪ�ե��륿�Υꥹ�ȥ�٥�γ�ĥ����
define('TRACKER_PLUS_LIST_APPLY_LISTFORMAT',TRUE);

//////////////////////////////////////////////////////////////////////////////
// ** TRACKER_PLUS FILTER CONSTANT DEFINISION ** 
// If you want to keep compatible to before 2.5, 
//   you should define below values "����" and "����" respectively.
define('TRACKER_PLUS_FILTER_AND_CONDITION', "����" );      // "����" or "AND"
define('TRACKER_PLUS_FILTER_EXCLUDE_CONDITION', "����");   // "����" or "EXCLUDE" 
//////////////////////////////////////////////////////////////////////////////
define('TRACKER_PLUS_FILTER_EXTENDED_SETTING_TITLE', "��ĥ���Ф�"); // "���Ф�����" or "EXTENTED_TITLE" 

function plugin_tracker_plus_init()
{
	switch (LANG) {
	case 'ja' :
		$msg = plugin_tracker_plus_init_ja();
		break;
	default:
		$msg = plugin_tracker_plus_init_en();
	}
	set_plugin_messages($msg);
}

function plugin_tracker_plus_init_ja()
{
	$msg = array(
		'_msg_tracker_plus_list_filter_label' => "�ʤ���߾�����",
		'_msg_tracker_plus_list_nodata' => "������ɽ��������ܤϤ���ޤ���.",
	);
	return $msg;
}

function plugin_tracker_plus_init_en()
{
	$msg = array(
		'_msg_tracker_plus_list_filter_label' => "Tracker_List FilterList",
		'_msg_tracker_plus_list_nodata' => "There is no item displayed in List.",
	);
	return $msg;
}

function plugin_tracker_plus_convert()
{
	global $script,$vars;

	if ( defined('PKWK_READONLY') && PKWK_READONLY) return ''; // Show nothing

	$base = $refer = $vars['page'];

	$config_name = 'default';
	$form = 'form';

	$isDatefield = FALSE;

	if (func_num_args())
	{
		$options = array();
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

		unset($options, $args);
	}

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		return "<p>config file '".htmlspecialchars($config_name)."' not found.</p>";
	}

	// (Attension) Config Class don't have config_name member. This make extra definision. (jjyun)
	$config->config_name = $config_name;

	$fields = plugin_tracker_plus_get_fields($base,$refer,$config);

	$form = $config->page.'/'.$form;
	
	if (!is_page($form))
	{
		return "<p>config file '".make_pagelink($form)."' not found.</p>";
	}
	$retval = convert_html(plugin_tracker_plus_get_source($form));
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
		$number = plugin_tracker_plus_getNumber();
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

function plugin_tracker_plus_getNumber() {
	global $vars;
	static $numbers = array();
	if (!array_key_exists($vars['page'],$numbers))
	{
		$numbers[$vars['page']] = 0;
	}
	return $numbers[$vars['page']]++;
}

function plugin_tracker_plus_action()
{
	global $post, $now;

	if ( defined('PKWK_READONLY') && PKWK_READONLY) die_message('PKWK_READONLY prohibits editing');

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

	// org: QuestionBox3/211: Apply edit_auth_pages to creating page
	edit_auth($page,true,true);

	// �ڡ����ǡ���������
	$postdata = plugin_tracker_plus_get_source($source);

	// ����Υǡ���
	$_post = array_merge($post,$_FILES);
	$_post['_date'] = $now;
	$_post['_page'] = $page;
	$_post['_name'] = $name;
	$_post['_real'] = $real;
	// $_post['_refer'] = $_post['refer'];

	$fields = plugin_tracker_plus_get_fields($page,$refer,$config);

	// Creating an empty page, before attaching files
       	touch(get_filename($page));

	foreach (array_keys($fields) as $key)
	{
	        // modified for hidden2 by jjyun
		// $value = array_key_exists($key,$_post) ?
		// 	$fields[$key]->format_value($_post[$key]) : '';
	        $value = '';
		if( array_key_exists($key,$_post) )
		{
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

	if (function_exists('pkwk_headers_sent'))pkwk_headers_sent();
	header('Location: ' . get_script_uri() . '?' . $r_page);
	exit;
}

// �ե�����ɥ��֥������Ȥ��ۤ���
function plugin_tracker_plus_get_fields($base,$refer,&$config)
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
                 '_submit'=>'submit_plus', // �ɲåܥ���
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


///////////////////////////////////////////////////////////////////////////
// ����ɽ��
function plugin_tracker_plus_list_convert()
{
	global $vars;

	$config = 'default';
	$page = $refer = $vars['page'];
	$field = '_page';
	$order = '_real:SORT_DESC';
	$list = 'list';
	$limit = NULL;
	$filter_name = '';
	$cache = TRACKER_PLUS_LIST_CACHE_DEFAULT;

	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
		        case 6:
			        $cache = is_numeric($args[5]) ? $args[5] : $cache;
		        case 5:
			        $filter_name = $args[4];
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

	return plugin_tracker_plus_getlist($page,$refer,$config,$list,$order,$limit,$filter_name,$cache);
}

function plugin_tracker_plus_list_action()
{
	global $script,$vars,$_tracker_messages;

	$page = $refer = $vars['opage'];
	$orefer = $vars['orefer'];
	if( $orefer == NULL) $orefer = $refer;
	$s_orefer = make_pagelink($orefer);
	$config = $vars['config'];
	$list = array_key_exists('list',$vars) ? $vars['list'] : 'list';
	$order = array_key_exists('order',$vars) ? $vars['order'] : '_real:SORT_DESC';
	$filter_name = array_key_exists('filter',$vars) ? $vars['filter'] : NULL;

	$cache = isset($vars['cache']) ? $vars['cache'] : NULL;
	$dynamicFilter = isset($vars['dynamicFilter']) ? true : false;

	// this delete tracker caches. 
	if( $cache == 'DELALL' )
	{
		if(! Tracker_plus_list::delete_caches('(.*)(.tracker)$') )
		  die_message( CACHE_DIR . ' is not found or not readable.');

		return array(
			     'result' => FALSE,
			     'msg' => 'tracker_plus_list caches are cleared.',
			     'body' =>'tracker_plus_list caches are cleared.',
			     );
	}

	if( $dynamicFilter )
	{
		$filter_name = $vars['value'];
	}
	
	return array(
		     'msg' => str_replace('$1',$page,$_tracker_messages['msg_list']),
		     'body'=> str_replace('$1',$s_orefer,$_tracker_messages['msg_back']).
		     plugin_tracker_plus_getlist($page,$refer,$config,$list,$order,NULL,$filter_name,$cache, $orefer)
	);
}

function plugin_tracker_plus_getlist($page, $refer, $config_name, $list_name, $order = '', $limit = NULL,
				     $filter_name = NULL, $cache, $orefer = NULL)
{
	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		return "<p>config file '".htmlspecialchars($config_name)."' is not exist.";
	}

	$config->config_name = $config_name;

	if (!is_page($config->page.'/'.$list_name))
	{
		return "<p>config file '".make_pagelink($config->page.'/'.$list_name)."' not found.</p>";
	}

	// Attension: Original code use $list as another use in this. (before this, $list contains list_name). by jjyun.
	$list = &new Tracker_plus_list($page,$refer,$config,$list_name,$filter_name,$cache,$orefer);

	$filter_selector = '';
	if($list->filter_name != NULL || $list->dynamic_filter == TRUE )
	{
	        $filter_config = new Tracker_plus_FilterConfig('plugin/tracker/'.$config_name.'/filters');

		if( ! $filter_config->read() && $list->filter_name != NULL)
		{
		        // filter�����꤬�ʤ���Ƥ��ʤ����, ���顼�����֤�
		        return "<p>config file '".htmlspecialchars($config->page.'/filters')."' not found</p>";
		}

		$list_filter = &new Tracker_plus_list_filter($filter_config, $list->filter_name, $list->dynamic_filter);
		
		$filter_selector = $list->get_selector($list_filter);
		$list_filter->filter($list);
		unset($list_filter);
	}

	$list->sort($order);
	
	return $filter_selector . $list->toString($limit);
}	

class Tracker_plus_FilterConfig extends Config
{

	function Tracker_plus_FilterConfig($name)
	{
		parent::Config($name);
	}

	function get_keys()
	{
		return array_keys($this->objs);
	}

	function after_read($title)
	{
		$after_obj = NULL;
		$obj = & $this->get_object($title);

		foreach( $obj->after as $after_line )
		{
			if( $after_line == '') continue;

    			if ($after_line{0} == '|' && preg_match('/^\|(.+)\|([fF]?)\s*$/', $after_line, $matches) )
			{
				// Table row
				if (! is_a($after_obj, 'ConfigTable_Sequential') )
					$after_obj = & new ConfigTable_Sequential('', $after_obj);
				// Trim() each table cell
				$after_obj->add_value(array_map('trim', explode('|', $matches[1])));
			}

		}

		return $after_obj->values;
	}
}


// �������饹
class Tracker_plus_list extends Tracker_list
{
	var $filter_name;
	var $dynamic_filter = TRACKER_PLUS_LIST_DYNAMIC_FILTER_DEFAULT;
	var $orefer;
	var $cache_level = array(
			   'NO'  => 0, // ����å�����å������Ѥ��ʤ�
			   'LV1' => 1, // �ڡ������ɤ߹��߽������Ф��륭��å����ͭ���ˤ���
			   'LV2' => 2, // html���Ѵ���Υǡ����Υ���å����ͭ���ˤ���
			   );

	var $cache = array('level' => TRACKER_PLUS_LIST_CACHE_DEFAULT ,
			   'state' => array('stored_total' => 0,  // cache�ˤ���ǡ�����
					    'hits' => 0,          // cache��ˤ���ͭ���ʥǡ�����
					    'total' => 0,         // ����ʬ��ޤ�ƺǽ�Ū��ͭ���ʥǡ�����
					    'cnvrt' => FALSE), 
			   'verbs' => FALSE,
			   );

	function Tracker_plus_list($page,$refer,&$config,$list,$filter_name,$cache,$orefer)
	{
		$this->page = $page;
		$this->config = &$config;
		$this->list = $list;
		$this->refer = $refer;
		$this->orefer = ($orefer != NULL) ? $orefer : $refer;

		if( preg_match("/^[+|-](.*)/", $filter_name, $matches) )
		{
			$this->filter_name = $matches[1];
			$this->dynamic_filter = ( ord($filter_name) == ord("+") ) ? true : false;
		}
		else
		{
			$this->filter_name = $filter_name;
			if ( strlen($this->filter_name) == 0 )
			{
				$this->dynamic_filter = false;
			}
		}

		$this->fields = plugin_tracker_plus_get_fields($page,$refer,$config);
		
		$pattern = join('',plugin_tracker_plus_get_source($config->page.'/page'));
		
		// "/page"�����Ƥ�Ĺ�������preg_match()�����Ԥ���Х�(?)������Τ�
		// "//////////"�ޤǤ�ޥå��оݤȤ�����
		// ( see http://dex.qp.land.to/pukiwiki.php, Top/Memo/Wiki���)
		$pattern_endpos = strpos($pattern, "//////////");
		if($pattern_endpos > 0){
			$pattern = substr($pattern, 0, $pattern_endpos);
		}
		
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
				if (preg_match(TRACKER_PLUS_LIST_EXCLUDE_PATTERN,$name))
				{
					continue;
				}
				$this->add($_page,$name);
			}
		}
		$this->cache['state']['total'] = count($this->rows);
                $this->put_cache_rows();
        }

	// over-wride function.
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
			// BugTrack2/106: Only variables can be passed by reference from PHP 5.0.5
			$order_keys = array_keys($order); // with array_shift();

			$index = array_flip($order_keys);
			$pos = 1 + $index[$sort];
			$b_end = ($sort == array_shift($order_keys));
			$b_order = ($order[$sort] == SORT_ASC);
			$dir = ($b_end xor $b_order) ? SORT_ASC : SORT_DESC;
			$arrow = '&br;'.($b_order ? '&uarr;' : '&darr;')."($pos)";

			unset($order[$sort], $order_keys);
		}
		$title = $this->fields[$field]->title;

		$r_opage = rawurlencode($this->page);
		$r_orefer = rawurlencode($this->orefer);
		$r_config = rawurlencode($this->config->config_name);
		$r_list = rawurlencode($this->list);
		$_order = array("$sort:$dir");
		if (is_array($order))
			foreach ($order as $key=>$value)
				$_order[] = "$key:$value";
		$r_order = rawurlencode(join(';',$_order));

		// attension : if you modified this, you should also see $this->get_selector()
		$_filter = ($this->dynamic_filter) ? "+". $this->filter_name : $this->filter_name; 
		$r_filter = rawurlencode($_filter);

		$_cache = ( $this->cache['level'] == $this->cache_level['LV2'] ) ? $this->cache_level['LV1'] : $this->cache['level'];
		if( $this->cache['verbs'] ) $_cache = -1 * $_cache;
		$r_cache = rawurlencode($_cache);
		
		return "[[$title$arrow>$script?plugin=tracker_plus_list&opage=$r_opage&orefer=$r_orefer&config=$r_config&list=$r_list&order=$r_order&filter=$r_filter&cache=$r_cache]]";
	}

	function get_selector($filter)
	{
		global $_msg_tracker_plus_list_filter_label;

		if( ! $filter->filter_select ) return "";
		if( $orefer == NULL) $orefer = $refer;

		$optionsHTML = $filter->get_options_html();

		$s_refer = htmlspecialchars($this->refer);
		$s_orefer = htmlspecialchars($this->orefer);
		$s_opage = htmlspecialchars($this->page);
		$s_config = htmlspecialchars($this->config->config_name);
		$s_list = htmlspecialchars($this->list);
		$s_order = htmlspecialchars($this->$order);
		$s_filter = htmlspecialchars($filter->name);

		$_cache = ( $this->cache['level'] == $this->cache_level['LV2'] ) ? $this->cache_level['LV1'] : $this->cache['level'];
		if( $this->cache['verbs'] ) $_cache = -1 * $_cache;
		$s_cache = htmlspecialchars($_cache);

		$selector_html = <<< EOD
<form action="$s_script" method="get" style="margin:0;">
<div>
$_msg_tracker_plus_list_filter_label : <select name="value" style="vertical-align:middle" onchange="this.form.submit();">
$optionsHTML
</select>
<input type="hidden" name="plugin" value="tracker_plus_list" />
<input type="hidden" name="refer"  value="$s_refer" />
<input type="hidden" name="opage"  value="$s_opage" />
<input type="hidden" name="orefer" value="$s_orefer" />
<input type="hidden" name="config" value="$s_config" />
<input type="hidden" name="list"   value="$s_list" />
<input type="hidden" name="order"  value="$s_order" />
<input type="hidden" name="filter" value="$s_filter" />
<input type="hidden" name="cache"  value="$s_cache" />
<input type="hidden" name="dynamicFilter" value="on" />
</div>
</form>
EOD;
		return $selector_html;
	}

	// over-wride function.
	function toString($limit=NULL)
	{
		global $_tracker_messages;
		global $_msg_tracker_plus_list_nodata;

		$source = '';
		$sort_types = '';

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
			return $_msg_tracker_plus_list_nodata;
		}

		$htmls = $this->get_cache_cnvrt();
		if( strlen($htmls) > 0 )
		{
			return $htmls;
		}

		// This case is cache flag or status is not valie.
		foreach (plugin_tracker_plus_get_source($this->config->page.'/'.$this->list) as $line)
		{
			if (preg_match('/^\|(.+)\|[hHfFcC]$/',$line))
			{
				$source .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'replace_title'),$line);

  				// for sortable table
//   				if(preg_match('/^\|(.+)\|[hH]$/',$line))
//   				{
//   					$sort_types .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'get_sort_type'),$line);
//   				}
			}
			else
			{
				$body[] = $line;
			}
		}

		$lineno = 1;
		foreach ($this->rows as $key=>$row)
		{
			if (!TRACKER_PLUS_LIST_SHOW_ERROR_PAGE and !$row['_match'])
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

		/////////////////////////////////
		// for sortable_script
		// It work, but It isn't currectly , why? (2005/05/06)
		// $htmls = $this->make_sortable_script($htmls, $sort_types);

		$this->put_cache_cnvrt($htmls);

		if($this->cache['verbs'] == TRUE) 
		{
			$htmls .= $this->get_verbose_cachestatus();
		}

		return $htmls;
	}

	// original functions.
	function make_sortable_script($htmls,$sort_types)
	{
		$js = '<script type="text/javascript" src="' . SKIN_DIR . 'sortabletable.js"></script>';

		$sort_types = convert_html($sort_types);
		$sort_types = strip_tags($sort_types);
		
		$sort_types_arr = array();
		$sort_types_arr = explode(",", $sort_types);
		array_pop($sort_types_arr);

		$sort_types_str = '"' . implode($sort_types_arr,'","') . '"';

		$number = plugin_tracker_plus_getNumber();
		$htmls = strstr($htmls, "<thead>");
		$htmls = '<div class="ie5"><table class="style_table" id="tracker_plus_list_' . $number . '" cellspacing="1" border="0">' . $htmls;
		if( $number == 0 ) $htmls = $js . $htmls;

 		$htmls .= <<<EOF
<script type="text/javascript">
var st1 = new SortableTable(document.getElementById("tracker_plus_list_$number"), [$sort_types_str]);
</script>
EOF;
		return $htmls;
	}

	function get_sort_type($arr)
	{
		$field = $arr[1];
		if (!array_key_exists($field,$this->fields))
		{
			return $arr[0];
		}
		$sort_type = $this->fields[$field]->sort_type;

 		if( $sort_type == SORT_NUMERIC )
 		{
 			$ret= 'Number';
 		}
 		else if ( $sort_type == SORT_STRING )
 		{
 			$ret = 'String';
 		}
 		else if ( $sort_type == SORT_REGULAR )
 		{
 			$ret = 'CaseInsensitiveString';
 		}
 		else
 		{
 			$ret= 'Number';
 		}

		return "$ret,";
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
                foreach (plugin_tracker_plus_get_source($this->config->page.'/'.$this->list) as $line)
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

function plugin_tracker_plus_get_source($page)
{
	return plugin_tracker_get_source($page);
}

// I want to make Tracker_list_filter and Tracker_list_filterCondition to
// inner class of Tracker_list. But inner class is supported by PHP5, not PHP4.(jjyun)
class Tracker_plus_list_filter
{
	var $name;
	var $filter_select;
	var $conditions = array();
	var $config;
  
	function Tracker_plus_list_filter($filter_config, $name = NULL, $filter_select = FALSE)
	{
		$this->config = $filter_config;  
		$this->name = $name;
		$this->filter_select = $filter_select;
		foreach( $filter_config->get($name) as $filter )
		{
			array_push( $this->conditions,
				    new Tracker_plus_list_filterCondition($filter, $name) );
		}
	}

	function filter(& $list)
	{
		if( $this->name != NULL)
		{
			$list->rows = array_filter($list->rows, array($this, "judge" ));
		}
	}

	function judge($var)
	{
		$counter = 0;  
		$condition_flag = true;
		foreach($this->conditions as $condition)
		{
			if($condition->is_cnctlogic_AND or $counter == 0)
			{
				$condition_flag = ($condition->filter($var) and $condition_flag );
			}
			else
			{  
				$condition_flag = ($condition->filter($var)  or $condition_flag );
			}
			$counter++ ;
		}
		return $condition_flag;
	}

	function get_options_html()
	{
		$optionsHTML = '';
		$isSelect = false;
		$keys = $this->config->get_keys();

		// attension : if you modified this, you should see Tracker_plus_list::replace_title()
		$d_select = ($this->filter_select) ? "+" : "";

		foreach( $keys as $option) 
		{
			if( $option == "" )
				continue;

			$encodedOption = htmlspecialchars($option);
			list($label, $style) = ( TRACKER_PLUS_LIST_APPLY_LISTFORMAT ) ? 
							$this->get_option_style($option) : array($encodeOption ,"");
			
			if( $option == $this->name)
			{
				$isSelect = true;
				$optionsHTML .= "<option value='$d_select$encodedOption' $style selected='selected'>$label</option>";
			}
			else
			{
				$optionsHTML .= "<option value='$d_select$encodedOption' $style>$label</option>";
			}
		}
	
		$allSelected = ( $isSelect ) ? "" : "selected='selected'" ;
		$optionsHTML = "<option value='SelectAll' $allSelected >SelectAll</option>"  . $optionsHTML;
		
		return $optionsHTML;
	}

	function get_option_style($filter_name)
	{
		$s_label = $filter_name;
		$s_format = "";

		foreach( $this->config->after_read($filter_name) as $options)
		{
			if($options[0] != TRACKER_PLUS_FILTER_EXTENDED_SETTING_TITLE ) continue;

			$s_label=$options[1];
			$s_format=$options[2];

			if( $s_format == '' ) break;

			$format_enc = htmlspecialchars($s_format);
			$format_enc = preg_replace("/\%s/", '', $format_enc);

			$opt_format='';
			$matches=array();
			while ( preg_match('/^(?:(BG)?COLOR\(([#\w]+)\)):(.*)$/', $format_enc, $matches))
			{
				if ($matches[0])
				{
					$style_name = $matches[1] ? 'background-color' : 'color';
					$opt_format .= $style_name . ':' . htmlspecialchars($matches[2]) . ';';
					$format_enc = $matches[3];
				}
			}
			$s_format = 'style='.$opt_format;
		}

		return array($s_label, $s_format);
	}

}
class Tracker_plus_list_filterCondition
{
	var $name;
	var $target;
	var $matches;
	var $is_exclued;
	var $is_cnctlogic_AND;
  
	function Tracker_plus_list_filterCondition($field,$name)
	{
		$this->name = $name;
		$this->is_cnctlogic_AND = ($field[0] == TRACKER_PLUS_FILTER_AND_CONDITION ) ? true : false ;
		$this->target = $field[1];
		$this->matches = preg_quote($field[2],'/');
		$this->matches = implode(explode(',',$this->matches) ,'|');
		$this->is_exclued = ($field[3] == TRACKER_PLUS_FILTER_EXCLUDE_CONDITION ) ? true : false ;
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
			$options[$this->name] = array_flip( array_map(create_function('$arr','return $arr[0];'),
								      $this->config->get($this->name) ) );
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
		$arg= Tracker_plus_field_string_utility::get_argument_from_block_type_plugin_string($str);

		// config�ڡ��������ꤵ�줿°���ͤ���Ӥ���
		foreach ($this->config->get($this->name) as $option)
		{
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
  
	function extract_value($value)
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
		$arg = Tracker_plus_field_string_Utility::get_argument_from_plugin_string(
			    $value, $extract_arg_num, $target_plugin_name, $target_plugin_type);

		return $arg;
	}

	// (sort��Ŭ�ѻ��ˡ����Ѥ���Ƥ���)
	// ����(page��γ�����ʬ)���Ф��ơ�config�ڡ����Υ��ץ�������˽��äơ�
	// �֥�å����Υץ饰�������������ꤵ�줿��ʬ��ʸ�����
	// �ڤ�Ф����ͤ��֤�������ޤ�
	function get_value($value)
	{
		return $this->extract_value($value);
	}
	  
	// ����(page��γ�����ʬ)���Ф��ơ�
	// config�ڡ����Υ��ץ�������˽��ä��ڤ�Ф����ͤ��֤���
	// page��γ�����ʬ��config�ڡ�����°���Ͱ���������������Ǥ��ޤޤ��С�
	// ���θ��Ф����ͤ��֤���style��Ŭ�ѻ��ˡ����Ѥ���Ƥ����
	function get_key($str)
	{
		// ����(page��γ�����ʬ)���Ф��ơ�config�ڡ����Υ��ץ�������˽��ä��ڤ�Ф���
		$str= $this->extract_value($str);
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
		return $this->extract_value($str);
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
	function extract_value($value)
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
		$arg = Tracker_plus_field_string_Utility::get_argument_from_plugin_string(
			    $value, $extract_arg_num, $target_plugin_name, $target_plugin_type);

		// ��Ф���ʸ���󤫤���ͤǤ�����ʬ�Τߤ���Ф��������Ȥ�ɽ���ν����оݤȤ���
		$arg = (preg_match("/(\d+)/",$arg,$match) ) ? $match[1] : 0;

		return $arg;
	}
}

class Tracker_field_hidden2select extends Tracker_field_hidden2
{
	var $sort_type = SORT_NUMERIC;

	function get_value($value)
	{
		return Tracker_field_select2::get_value($value);
	}

}

class Tracker_field_submit_plus extends Tracker_field
{
	function get_tag()
	{
		$s_title = htmlspecialchars($this->title);
		$s_page = htmlspecialchars($this->page);
		$s_refer = htmlspecialchars($this->refer);
		$s_config = htmlspecialchars($this->config->config_name);
		
                return <<<EOD
<input type="submit" value="$s_title" />
<input type="hidden" name="plugin" value="tracker_plus" />
<input type="hidden" name="_refer" value="$s_refer" />
<input type="hidden" name="_base" value="$s_page" />
<input type="hidden" name="_config" value="$s_config" />
EOD;
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
		
		// �ǥե�����ͤ򸽺ߤ����դˤ���
		if($s_value=="NOW")
		{
		  $s_value = $this->get_datestr_with_format($s_format, $s_year, $s_month, $s_date);
		}
		// Javascript�˰����錄�������Υե����ޥå�ʸ������ѹ�����
		$s_format = $this->form_format($s_format);
		
		return <<<EOD
<input type="text" name="$s_name" size="$s_size" value="$s_value" />
<input type="button" value="..." onclick="dspCalendar(this.form.$s_name, event, $s_format, 0 , $s_year, $s_month-1, $s_date, 0);" />
EOD;
	}
  
	// sort��Ŭ�ѻ��ˡ������ͤ�ʤäƽ�����Ԥ碌�롣
	// ������ʬ�˴ޤޤ��֥�å��ץ饰�����0���ܤΰ������֤�
	function get_value($value)
	{
		$arg= Tracker_plus_field_string_utility::get_argument_from_block_type_plugin_string($value,0,'datefield');
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

	function get_datestr_with_format($format_opt,$yyyy,$mm,$dd ){
		// �����η���ͤ��ϰ� month is 1 - 12
		$strWithFormat = $format_opt;
		$yy = $yyyy%100;
		
		$yy = sprintf("%02d",$yy);
		$mm = sprintf("%02d",$mm);
		$dd = sprintf("%02d",$dd);
		$strWithFormat = preg_replace('/YYYY/i', $yyyy, $strWithFormat);
		$strWithFormat = preg_replace('/YY/i',   $yy,   $strWithFormat);
		$strWithFormat = preg_replace('/MM/i',   $mm,   $strWithFormat);
		$strWithFormat = preg_replace('/DD/i',   $dd,   $strWithFormat);

		return $strWithFormat;
	}

	function set_head_declaration() {
		global $pkwk_dtd, $javascript;  
		
		// XHTML 1.0 Transitional
		if (! isset($pkwk_dtd) || $pkwk_dtd == PKWK_DTD_XHTML_1_1)
			$pkwk_dtd = PKWK_DTD_XHTML_1_0_TRANSITIONAL;
		
		// <head> ������ؤ� <meta>������ɲ�
		$javascript = TRUE;
	}
}

class Tracker_plus_field_string_utility {
  
  	function get_argument_from_block_type_plugin_string($str,
						      $extract_arg_num = 0,
						      $plugin_name = '.*' ) {
	  return Tracker_plus_field_string_utility::get_argument_from_plugin_string($str,$extract_arg_num,$plugin_name,'block');
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
