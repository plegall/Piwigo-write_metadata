<?php
/*
Plugin Name: Write Metadata
Description: Write Piwigo photo properties (title, description, author, tags) into IPTC fields
Author: plg
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=769
Version: 2.8.a
*/

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

defined('WRITE_METADATA_ID') or define('WRITE_METADATA_ID', basename(dirname(__FILE__)));
define('WRITE_METADATA_PATH' , PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/');

// +-----------------------------------------------------------------------+
// | Edit Photo                                                            |
// +-----------------------------------------------------------------------+

add_event_handler('loc_begin_admin_page', 'wm_add_link', 60);
function wm_add_link()
{
	global $template, $page;

	$template->set_prefilter('picture_modify', 'wm_add_link_prefilter');

  if (isset($page['page']) and 'photo' == $page['page'])
  {
    $template->assign(
      'U_WRITEMETADATA',
      get_root_url().'admin.php?page=photo-'.$_GET['image_id'].'-properties&amp;write_metadata=1'
      );
  }
}

function wm_add_link_prefilter($content, &$smarty)
{
  $search = '{if !url_is_remote($PATH)}';
  
  $replacement = '{if !url_is_remote($PATH)}
<li><a class="icon-arrows-cw" href="{$U_WRITEMETADATA}">{\'Write metadata\'|@translate}</a></li>';

  return str_replace($search, $replacement, $content);
}

add_event_handler('loc_begin_admin_page', 'wm_picture_write_metadata');
function wm_picture_write_metadata()
{
  global $page, $conf;

  load_language('plugin.lang', dirname(__FILE__).'/');
  
  if (isset($page['page']) and 'photo' == $page['page'] and isset($_GET['write_metadata']))
  {
    check_input_parameter('image_id', $_GET, false, PATTERN_ID);
    wm_write_metadata($_GET['image_id']);

    $_SESSION['page_infos'][] = l10n('Metadata written into file');
    redirect(get_root_url().'admin.php?page=photo-'.$_GET['image_id'].'-properties');
  }
}

// +-----------------------------------------------------------------------+
// | Batch Manager                                                         |
// +-----------------------------------------------------------------------+

add_event_handler('loc_begin_element_set_global', 'wm_element_set_global_add_action');
function wm_element_set_global_add_action()
{
  global $template, $page;
  
  $template->set_filename('writeMetadata', realpath(WRITE_METADATA_PATH.'element_set_global_action.tpl'));

  if (isset($_POST['submit']) and $_POST['selectAction'] == 'writeMetadata')
  {
    $page['infos'][] = l10n('Metadata written into file');
  }

  $template->assign(
    array(
      'WM_PWG_TOKEN' => get_pwg_token(),
      )
    );

  $template->append(
    'element_set_global_plugins_actions',
    array(
      'ID' => 'writeMetadata',
      'NAME' => l10n('Write metadata'),
      'CONTENT' => $template->parse('writeMetadata', true),
      )
    );
}

add_event_handler('ws_add_methods', 'wm_add_methods');
function wm_add_methods($arr)
{
  include_once(WRITE_METADATA_PATH.'ws_functions.inc.php');
}

// +-----------------------------------------------------------------------+
// | Common functions                                                      |
// +-----------------------------------------------------------------------+

/**
 * inspired by convert_row_to_file_exiftool method in ExportImageMetadata
 * class from plugin Tags2File. In plugin write_medata we just skip the
 * batch command file, and execute directly on server (much more user
 * friendly...).
 */
function wm_write_metadata($image_id)
{
  global $conf;
  
  $query = '
SELECT
    img.name,
    img.comment,
    img.author,
    img.date_creation,
    GROUP_CONCAT(tags.name) AS tags,
    img.path
  FROM '.IMAGES_TABLE.' AS img
    LEFT OUTER JOIN '.IMAGE_TAG_TABLE.' AS img_tag ON img_tag.image_id = img.id
    LEFT OUTER JOIN '.TAGS_TABLE.' AS tags ON tags.id = img_tag.tag_id
  WHERE img.id = '.$image_id.'
  GROUP BY img.id, img.name, img.comment, img.author, img.path
;';
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);

  $name = wm_prepare_string($row['name'], 256);
  $description = wm_prepare_string($row['comment'], 2000);
  $author = wm_prepare_string($row['author'], 32);
  $tags = wm_prepare_string($row['tags'], 64);

  $command = isset($conf['exiftool_path']) ? $conf['exiftool_path'] : 'exiftool';

  if (strlen($name) > 0)
  {
    # 2#105 in iptcparse($imginfo['APP13'])
    $command.= ' -IPTC:Headline="'.$name.'"';

    # 2#005 in iptcparse($imginfo['APP13'])
    $command.= ' -IPTC:ObjectName="'.wm_cutString($name, 64).'"';
  }

  if (strlen($description) > 0)
  {
    # 2#120 in iptcparse($imginfo['APP13'])
    $command.= ' -IPTC:Caption-Abstract="'.$description.'"';
  }

  if (strlen($author) > 0)
  {
    # 2#080 in iptcparse($imginfo['APP13'])
    $iptc_field = 'By-line';

    if (
      $conf['use_iptc']
      and isset($conf['use_iptc_mapping']['author'])
      and '2#122' == $conf['use_iptc_mapping']['author']
      )
    {
      # 2#122 in iptcparse($imginfo['APP13'])
      $iptc_field = 'Writer-Editor';
    }

    $command.= ' -IPTC:'.$iptc_field.'="'.$author.'"';
  }
  
  if (strlen($tags) > 0)
  {
    # 2#025 in iptcparse($imginfo['APP13'])
    $command.= ' -IPTC:Keywords="'.$tags.'"';
  }

  $command.= ' "'.$row['path'].'"';
  // echo $command;

  $exec_return = exec($command, $output);
  // echo '$exec_return = '.$exec_return.'<br>';
  // echo '<pre>'; print_r($output); echo '</pre>';

  return true;
}

function wm_prepare_string($string, $maxLen)
{
  return wm_cutString(
    wm_explode_description(
      wm_decode_html_string_to_unicode($string)
      ),
    $maxLen
    );
}

function wm_cutString($description, $maxLen)
{
  if (strlen($description) > $maxLen)
  {
    $description = substr($description, 0, $maxLen);
  }
  return $description;
}

function wm_explode_description($description)
{
  return str_replace(
    array('<br>', '<br />', "\n", "\r"),
    array('', '', '', ''),
    $description
    );
}

function wm_decode_html_string_to_unicode($string)
{
  if (isset($string) and strlen($string) > 0)
  {
    $string = html_entity_decode(trim($string), ENT_QUOTES, 'UTF-8');
    $string = stripslashes($string);
  }
  else
  {
    $string = '';
  }
  
  return($string);
}
?>
