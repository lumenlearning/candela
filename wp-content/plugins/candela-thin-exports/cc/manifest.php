<?php
namespace CC;
require_once('base.php');

class Manifest extends Base
{

  private $book_structure = null;
  private $version = null;
  private $manifest = null;
  private $link_template = null;
  private $is_inline = false;
  private $options = null;

  private $tmp_file = null;

  private static $templates = [
    "1.2" => [
      'manifest' => '../templates/cc_1_2/manifest.xml',
      'lti_link' => '../templates/cc_1_2/lti_link.xml',
    ],
    "1.3" => [
      'manifest' => '../templates/cc_1_3/manifest.xml',
      'lti_link' => '../templates/cc_1_3/lti_link.xml',
    ],
    "thin" => [
      'manifest' => '../templates/thin/manifest.xml',
      'lti_link' => '../templates/cc_1_3/lti_link.xml',
    ],
  ];

  public static $available_options = ['inline', 'include_fm', 'include_bm', 'export_flagged_only'];

  public function __construct($structure, $options=[])
  {
    $this->book_structure = $structure;
    $this->version = isset($options['version']) ? $options['version'] : 'thin';
    $this->manifest = self::get_manifest_template();
    $this->link_template = $this->get_lti_link_template();
    $this->options = $options;
  }

  public function build_manifest()
  {
    $this->manifest = str_replace('{course_name}', get_bloginfo('name'), $this->manifest);
    $this->manifest = str_replace('{course_description}', get_bloginfo('description'), $this->manifest);
    $this->manifest = str_replace('{organization_items}', $this->item_parts(), $this->manifest);
    $this->manifest = str_replace('{resources}', $this->item_resources(), $this->manifest);
  }

  public function build_zip(){
    $this->tmp_file = tempnam("tmp","thincc");
    $zip = new \ZipArchive();
    $zip->open($this->tmp_file,\ZipArchive::OVERWRITE);

    $zip->addFromString('imsmanifest.xml', $this->manifest);
    if(!$this->options['inline']) {
      $this->add_lti_link_files($zip);
    }

    $zip->close();

    return $this->tmp_file;
  }

  public function cleanup(){
    if( isset($this->tmp_file) && get_resource_type($this->tmp_file) === 'file' ){
      unlink($this->tmp_file);
    }
  }

  function __destruct(){
    $this->cleanup();
  }

  private function item_parts()
  {
    $items = '';
    $template = <<<XML

    <item identifier="%s">
      <title>%s</title>
      %s
    </item>
XML;

    foreach ($this->book_structure['part'] as $part) {
      $item_pages = $this->item_pages($part);
      if($item_pages != ''){
        $items .= sprintf($template, $this->identifier($part, "I_"), $part['post_title'], $item_pages);
      }
    }

    return $items;
  }

  private function item_pages($part)
  {
    $items = '';
    $template = <<<XML

        <item identifier="%s" identifierref="%s">
          <title>%s</title>
        </item>
XML;

    foreach ($part['chapters'] as $chapter) {
      if($this->export_page($chapter)) {
        $items .= sprintf($template, $this->identifier($chapter, "I_"), $this->identifier($chapter), $chapter['post_title']);
      }
    }

    return $items;
  }

  private function item_resources()
  {
    if($this->options['inline']) {
      return $this->inline_lti_resources();
    }else {
      return $this->referenced_lti_resources();
    }
  }

  private function inline_lti_resources(){
    $resources = "";
    $blog_id = get_current_blog_id();
    $base_url = get_site_url(1) . '/api/lti/' . $blog_id . '?page_title=%s';
    $template = <<<XML
        <resource identifier="%s" type="imsbasiclti_xmlv1p0">%s
        </resource>
XML;

    foreach ($this->book_structure['part'] as $part) {
      $part_base_url = sprintf($base_url, $part['post_name'] . '%%2F%s');
      foreach ($part['chapters'] as $chapter) {
        if($this->export_page($chapter)){
          $launch_url = sprintf($part_base_url, $chapter['post_name']);
          $resources .= sprintf("\n" . $template, $this->identifier($chapter), sprintf("\n" . $this->link_template, $chapter['post_title'], $launch_url));
        }
      }
    }

    return $resources;
  }

  private function referenced_lti_resources(){
    $resources = '';
    $template = <<<XML
        <resource identifier="%s" type="imsbasiclti_xmlv1p0">
            <file href="%s.xml"/>
        </resource>
XML;
    foreach ($this->book_structure['part'] as $part) {
      foreach ($part['chapters'] as $chapter) {
        if($this->export_page($chapter)) {
          $resources .= sprintf("\n" . $template, $this->identifier($chapter), $this->identifier($chapter));
        }
      }
    }

    return $resources;
  }

  private function add_lti_link_files($zip){
    $blog_id = get_current_blog_id();
    $base_url = get_site_url(1) . '/api/lti/' . $blog_id . '?page_title=%s';

    foreach ($this->book_structure['part'] as $part) {
      $part_base_url = sprintf($base_url, $part['post_name'] . '%%2F%s');
      foreach ($part['chapters'] as $chapter) {
        if($this->export_page($chapter)) {
          $launch_url = sprintf($part_base_url, $chapter['post_name']);
          $zip->addFromString($this->identifier($chapter) . '.xml', sprintf('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $this->link_template, $chapter['post_title'], $launch_url));
        }
      }
    }
  }

  private function export_page($page){
    if ($this->options['export_flagged_only']) {
      return $page['export'] == '1';
    } else {
      return true;
    }
  }

  public function __toString()
  {
    return $this->manifest;
  }

  private function get_manifest_template(){
    return file_get_contents(plugins_url(self::$templates[$this->version]['manifest'], __FILE__));
  }

  private function get_lti_link_template(){
    return file_get_contents(plugins_url(self::$templates[$this->version]['lti_link'], __FILE__));
  }

}
?>