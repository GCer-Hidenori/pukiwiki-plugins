<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: pages2csv.inc.php,v 0.71 2005/01/02 12:22:22 jjyun Exp $
// 
/////////////////////////////////////////////////
// �����Ԥ�����ź�եե�����򥢥åץ��ɤǤ���褦�ˤ���
define('PAGES2CSV_UPLOAD_ADMIN_ONLY',FALSE); // FALSE or TRUE
/////////////////////////////////////////////////

require_once( PLUGIN_DIR . 'tracker.inc.php');
require_once( PLUGIN_DIR . "attach.inc.php");

function plugin_pages2csv_init()
{
  if (function_exists('plugin_tracker_init'))
  {
      plugin_tracker_init();
  }
  
  $messages = array(
    '_pages2csv_messages' => array(
		   'btn_submit' => '�¹�',
		   'title_text' => 'CSV�ե�����������',
		   ),
    );
  set_plugin_messages($messages);
}

function plugin_pages2csv_convert()
{
  global $vars;
  global $_pages2csv_messages, $_attach_messages;
  
  $config = 'default';
  $page = $refer = htmlspecialchars($vars['page']);
  $order = '';
  $list = 'list';
  $limit = NULL;
  $filter = '';
  $encode = '';
  $extract = '';
  
  static $numbers = array();
  if (!array_key_exists($page,$numbers)) $numbers[$page] = 0;
  $pages2csv_no = $numbers[$page]++;
  
  // �����γ�ǧ������
  if (func_num_args())
    {
      $args = func_get_args();
      if ( count($args) == 0 ) return "<p>no option of config_name</p>";
      
      switch (count($args))
	{
	case 7:
	  $extract = $args[6];
	case 6:
	  $encode = $args[5];
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

  $config_obj = new Config('plugin/tracker/'.$config);
  if (!$config_obj->read())
  {
    return "<p>config file '".htmlspecialchars($config)."' not found.</p>";
  }
  unset($config_obj);
  
  $retval = '';
  $s_title  = htmlspecialchars($_pages2csv_messages['btn_submit']);
  $s_text   = htmlspecialchars($_pages2csv_messages['title_text']);
  
  $s_page   = htmlspecialchars($page);
  $s_config = htmlspecialchars($config);
  $s_list   = htmlspecialchars($list);
  $s_order  = htmlspecialchars($order);
  $s_limit  = htmlspecialchars($limit);
  $s_filter = htmlspecialchars($filter);
  $s_encode = htmlspecialchars($encode);
  $s_extract = htmlspecialchars($extract);

  $pass = '';
  // attach.inc.php �� ���åץ���/������˥ѥ���ɤ��׵᤹������Ǥ��ä���礫
  // �⤷���ϡ�CSV�ե�����κ���������Ԥ������Ԥ���褦�ˤ������
  if ( PLUGIN_ATTACH_PASSWORD_REQUIRE or PAGES2CSV_UPLOAD_ADMIN_ONLY)
    {
      $title = $_attach_messages[PAGES2CSV_UPLOAD_ADMIN_ONLY ?
				'msg_adminpass' : 'msg_password'];
      $pass = $title.': <input type="password" name="pass" size="8" />';
    }
  
  $retval .=<<<EOD
<input type="hidden" name="plugin" value="pages2csv" />
<input type="hidden" name="refer"  value="$refer" />
<input type="hidden" name="s_page" value="$s_page" />
<input type="hidden" name="config" value="$s_config" />
<input type="hidden" name="list"   value="$s_list" />
<input type="hidden" name="order"  value="$s_order" />
<input type="hidden" name="limit"  value="$s_limit" />
<input type="hidden" name="filter" value="$s_filter" />
<input type="hidden" name="encode" value="$s_encode" />
<input type="hidden" name="extract" value="$s_extract" />
EOD;

  return <<<EOD
<form enctype="multipart/form-data" action="$script" method="post">
 <div>
$s_text
<input type="submit" value="$s_title" />
$pass
<input type="hidden" name="_pages2csv_no" value="$pages2csv_no" />
$retval
 </div>
</form>
EOD;

}

function plugin_pages2csv_action()
{
  global $vars;
  global $_attach_messages;
  
  // ���Ѥ���ץ饰�����¸�ߥ����å�
  if ( !function_exists('pkwk_login') )
  {
    return array('result'=>FALSE,'msg'=>"pkwk_login function is not found (in func.php).");
  }

  // $vars['refer'] : ������plugin �����֤����ڡ���
  // $vars['s_page']  : �ꥹ��ɽ������tracker�Υڡ����γ�Ǽ���
  // �ڡ���̾�� NULL �Ǥ������¸�ߤ��ʤ�������
  // �ޤ� $pass �� �����ޤǥ桼��¦����Υѥ���ɥե졼����ɽ�����ȤȤ��롣
  $s_page = array_key_exists('s_page',$vars) ? htmlspecialchars($vars['s_page']) : NULL;
  $refer = array_key_exists('refer',$vars) ? htmlspecialchars($vars['refer']) : NULL;
  $pass = array_key_exists('pass',$vars) ? htmlspecialchars($vars['pass']) : NULL;

  // Authentication
  if ( ! is_pagename($refer) ) 
    return array('result'=>FALSE,'msg'=>$_attach_messages['err_noparm']);
  else if (! is_pagename($s_page) ) 
    return array('result'=>FALSE,'msg'=> "$s_page page is not exist.");
  else {
    check_editable($refer);  // ź����Υڡ������񤭹��߲�ǽ����
    check_readable($s_page); // ������Υڡ������ɤ߹��߲�ǽ����
  }

  if (PAGES2CSV_UPLOAD_ADMIN_ONLY and ($pass === NULL or ! pkwk_login($pass) ))
  {
    return   array('result'=>FALSE,'msg'=>$_attach_messages['err_adminpass']);
  }

  // Upload
  return plugin_pages2csv_upload($vars, $refer, $s_page, $pass);  
}

function plugin_pages2csv_upload($vars, $refer, $s_page, $pass)
{
  global $_attach_messages;

  // ���Ѥ���ץ饰�����¸�ߥ����å�
  if (!exist_plugin('attach') or !function_exists('attach_upload')  )
  {
    return array('msg'=>'attach.inc.php not found or not correct version.');
  }

  // �����ѥ��󥳡��ɤΥ��ݡ��Ȱ���(PHP4�λ��ͽ���)
  $supported_encodes = array("EUC-JP"=>"euc","SJIS"=>"sjis","JIS"=>"jis","UTF-8"=>"utf8");

  // �Ƽ�ѥ�᡼�����ɤ߹���
  $list = array_key_exists('list',$vars)   ? htmlspecialchars($vars['list']) : 'list';
  $order = array_key_exists('order',$vars) ? htmlspecialchars($vars['order']) :'_real:SORT_DESC';
  $encode = array_key_exists('encode',$vars) ? htmlspecialchars($vars['encode']) : NULL ;
  $limit = array_key_exists('limit',$vars) ? htmlspecialchars($vars['limit']) : NULL ;
  if( !is_numeric($limit) ) $limit= NULL;  // limit �Ͽ��ͥǡ������뤿��

  $config_name = array_key_exists('config',$vars) ? htmlspecialchars($vars['config']) : 'default';
  $filter_name = array_key_exists('filter',$vars) ? htmlspecialchars($vars['filter']) : NULL ;
  $extract_name = array_key_exists('extract',$vars) ? htmlspecialchars($vars['extract']) : NULL;

  // tracker �� config����
  $config = new Config('plugin/tracker/'.$config_name);
  if(!$config->read()) {
    return array( 'result' => FALSE,
		  'msg' => "<p>config file '". htmlspecialchars($config_name). "' is not exist.",
		  );
  }
  $config->config_name = $config_name;

  // tracker_list �� filter����
  $list_filter = NULL;
  if($filter_name != NULL) {
    $filter_config = new Config('plugin/tracker/'.$config->config_name.'/filters');
    
    if(!$filter_config->read()) {
      // filter �����꤬�ʤ���Ƥ��ʤ���С����顼�����֤�
      return array( 'result' => FALSE,
		    'msg' => "<p>config file '".htmlspecialchars($config->page.'/filters')."' not found</p>",
		   );
    }
    $list_filter = &new Tracker_list_filter($filter_config, $filter_name);
  }
  unset($filger_config);

  // pages2csv �� extract����
  $extract_arg_filter = NULL;
  if($extract_name != NULL) {
    $extract_config = new Config('plugin/pages2csv');
    
    if(!$extract_config->read()) {
      // extract �����꤬�ʤ���Ƥ��ʤ���С����顼�����֤�
      return array( 'result' => FALSE,
		    'msg' => "<p>config file '".htmlspecialchars($extract_config->page)."' not found</p>",
		    );
    }
    $extract_arg_filter = new Pages2csv_extract_arg_filter($extract_name, $extract_config);
  }
  unset($extract_config);

  // �������Ƥμ���
  $pstr = plugin_pages2csv_getcsvlist($s_page,$refer,$config,$list,$order,$limit,
				      $list_filter, $extract_arg_filter);

  // �������ƤΥ��󥳡��ǥ��󥰽���
  if ($encode != '' ) {
    if( !function_exists('mb_convert_encoding') )
      return   array('result'=>FALSE,'msg'=>'mb_convert_encoding is not installed.\n');
    else if(! array_key_exists($encode,$supported_encodes) )
      return   array('result'=>FALSE,'msg'=>"\"$encode\" is not suppoted.\n");
    else 
      $pstr=mb_convert_encoding($pstr,$encode);
  }

  // �ƥ�ݥ��ե�����ؤν���
  $tempname = tempnam("","pages2csv_temp");
  $fp = fopen($tempname,"w");
  fwrite($fp,$pstr);
  fclose($fp);
  
  // ź�եե�����̾�ν���
  $csvfilename = "pages2csv_". date("ymdHi",time());
  if($encode != '')
    $csvfilename .= ".".$supported_encodes[$encode];
  $csvfilename .= ".csv";

  // �ʲ���attach.inc.php �� attach_upload()�ؿ��򻲹ͤˤ���
  // (��ͳ)�����ե����뤬HTTP POST�ǥ��åץ��ɤ����ե�����ǤϤʤ����ᡢ
  // �嵭�ؿ���ή�Ѥ��뤳�Ȥ��Ǥ��ʤ���
  if (filesize($tempname) > MAX_FILESIZE)
  {
    unlink($tempname);
    return   array('result'=>FALSE,'msg'=>$_attach_messages['err_exceed']);
  }

  $obj = &new AttachFile($refer,$csvfilename);
  if ($obj->exist)
  {
    unlink($tempname);
    return   array('result'=>FALSE,'msg'=>$_attach_messages['err_exists']);
  }
  if (rename($tempname,$obj->filename))
  {
    chmod($obj->filename,PLUGIN_ATTACH_FILE_MODE);
  }
  unlink($tempname);

  if (is_page($refer))
  {
    touch(get_filename($refer));
  }

  $obj->getstatus();
  $obj->status['pass'] = ($pass != NULL) ? md5($pass) : '';
  $obj->putstatus();

  return  array('result'=>TRUE,'msg'=>$_attach_messages['msg_uploaded']);
}


function plugin_pages2csv_getcsvlist($page,$refer,$config,$list,$order='', $limit=NULL,
				     $list_filter=NULL, $extract_arg_filter=NULL)
{
  $filter_name = is_null($list_filter) ? NULL : $list_filter->filter_name;

  $list = &new Pages2csv_Tracker_csvlist($page,$refer,$config,$list, $filter_name,
					 $extract_arg_filter);
  if($list_filter != NULL)
  {
    $list->rows = array_filter($list->rows, array($list_filter, 'filters') );
  }
  $list->sort($order);

  return $list->toString($limit);
}

// CSV�Ѱ������饹
class Pages2csv_Tracker_csvlist extends Tracker_list
{
  var $extract_arg_filter = NULL;

  function Pages2csv_Tracker_csvlist($page,$refer,$config,$list,$filter_name,
				     $extract_arg_filter)
  {
    $cache = NULL;

    // �ƥ��饹�Υ��󥹥ȥ饯����ƤӽФ��ƽ����
    $this->Tracker_list($page,$refer,$config,$list,$filter_name,$cache);

    $this->extract_arg_filter = $extract_arg_filter;
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
	  
	  // ���ꤷ���ץ饰���������ξ��ΰ���ʸ�������Ф���
	  if( $this->extract_arg_filter != NULL ) {
	    $str = $this->extract_arg_filter->extracts($str);
	  }

	  //	  if (array_key_exists($name,$this->fields))
	  //  {
	  //    $str = $this->fields[$name]->format_cell($str);
	  //  }
	}
      else
	{
	  $str = $arr[0];
	}

      // ����������Ϥ����
      // �����ǥץ饰����ΰ�������Ф��롣

      return $this->pipe ? str_replace('|','&#x7c;',$str) : $str;
      
    }
  
  function replace_title($arr)
    {
      // ����������Ϥ����
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
      
      if (array_key_exists($sort,$order))
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

      // ��󥯾���ʤɤϳ�����..
      return "$title$arrow";
    }
  
  function toString($limit=NULL)
    {
      global $_tracker_messages;
      
      $source = '';
      $body = array();
      
      if($limit != NULL and count($this->rows) > $limit)
	{
	  $source = str_replace(
				array('$1','$2'),
				array(count($this->rows),$limit),
				$_tracker_messages['msg_limit'])."\n";
	  $this->rows = array_splice($this->rows,0,$limit);
	}
      if(count($this->rows) == 0 )
	{
	  return '';
	}


      foreach(plugin_tracker_get_source($this->config->page . '/' . $this->list) as $line)
	{
	  if (preg_match('/^\|(.+)\|[hHfFcC]$/',$line))
	    {
	      // ɽ����Υѥ��׶��ڤ��CSV�������ڤ���ѹ�
	      $line = preg_replace('/\|/','","', $line); 
	      $line = preg_replace('/^",(.+),"h$/','$1',$line);

	      $source .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'replace_title'),$line);
	    }
	  else
	    {
	      // ɽ����Υѥ��׶��ڤ��CSV�������ڤ���ѹ�
	      $line = preg_replace('/\|/','","', $line); 
	      $line = preg_replace('/^",(.+),"$/','$1',$line);

	      $body[] = $line;
	    }
	}

      foreach($this->rows as $key=>$row)
	{ 
	  if (!TRACKER_LIST_SHOW_ERROR_PAGE and !$row['_match'])
	    {
	      continue;
	    }
	  $this->items = $row;
	  foreach ($body as $line)
	    {
	      if (trim($line) == '')
		{
		  $source .= $line;
		  continue;
		}
	      $this->pipe = ($line{0} == '|' or $line{0} == '\:');
	      $source .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'replace_item'),$line);
	    }
	}
      return $source;
    }
}

class Pages2csv_extract_arg_filter
{
	var $extract_conditions = array();

	function Pages2csv_extract_arg_filter($extract_name, $extract_config)
	{
		foreach( $extract_config->get($extract_name) as $extract )
		{
			array_push($this->extract_conditions,
				   new Pages2csv_extract_arg_Condition($extract, $extract_name) );
		}
	}

	function extracts($var)
	{
		foreach($this->extract_conditions as $extract_condition)
		{
		  $var = $extract_condition->extract($var);
		}
		return $var;
	}

	function toString()
	{
	        $ret = '';
		foreach($this->extract_conditions as $extract_condition)
		{
		  $ret .= $extract_condition->toString();
		}
		return $ret;
	}


}

class Pages2csv_extract_arg_Condition
{
	var $condition_name;
	var $target_plugin_name;
	var $target_plugin_type;  // block = 0 , inline = 1 (default = 0)
	var $extract_arg_num;

	function Pages2csv_extract_arg_Condition($field, $name)
	{
		$this->condition_name = $name;
		$this->target_plugin_name = $field[0];
		$this->target_plugin_type = ($field[1] == "inline") ? 1 : 0;
		$this->extract_arg_num = is_numeric($field[2]) ? $field[2] : NULL ;
	}
  
	function extract($var)
	{
		$str_plugin = ($this->target_plugin_type == 0) ? '\#' : '\&';
		$str_plugin .= $this->target_plugin_name;

		if (preg_match_all("/(?:$str_plugin\(([^\)]*)\))/", $var, $matches, PREG_SET_ORDER))
		{
			$paddata = preg_split("/$str_plugin\([^\)]*\)/", $var);
			$var = $paddata[0];
			foreach($matches as $i => $match)
			{
				$str_arg = $match[1];
				$args = array();
				$args = explode(",",$str_arg);
				if( $this->extract_arg_num != NULL && 
				    $this->extract_arg_num < count($args) )
				{
					$extract_arg = $args[ $this->extract_arg_num ];
				}
				else
				{
					$extract_arg = $str_plugin . $str_arg;
				}
			}
			$var .= $extract_arg . $paddata[$i+1];
		}
		return $var;
	}

	function toString()
	{
		$str = "name : $this->condition_name | "
		  . "target_plugin_name : $this->target_plugin_name | "
		  . "target_plugin_type : $this->target_plugin_type | "
		  . "extract_arg_num : $this->extract_arg_num | ";
		return $str;
	}
}
?>
