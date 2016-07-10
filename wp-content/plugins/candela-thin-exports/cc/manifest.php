<?php
namespace CC;
require_once('base.php');

class Manifest extends Base
{

  private $book_structure = null;
  private $version = null;
  private $manifest = null;
  private $lti_link_template = null;
  private $web_link_template = null;
  private $topic_template = null;
  private $assignment_template = null;
  private $use_page_name_launch_url = false;
  private $options = null;
  private $guids_cache = null;

  private $tmp_file = null;

  private static $templates = [
    "1.1" => [
      'manifest' => '/templates/cc_1_1/manifest.xml',
      'lti_link' => '/templates/cc_1_2/lti_link.xml',
      'topic' => '/templates/cc_1_1/topic.xml',
      'web_link' => '/templates/cc_1_1/web_link.xml',
      'assignment' => '/templates/cc_1_3/assignment.xml', // not actually valid in CC 1.1
    ],
    "1.2" => [
      'manifest' => '/templates/cc_1_2/manifest.xml',
      'lti_link' => '/templates/cc_1_2/lti_link.xml',
      'topic' => '/templates/cc_1_2/topic.xml',
      'web_link' => '/templates/cc_1_2/web_link.xml',
      'assignment' => '/templates/cc_1_3/assignment.xml', // not actually valid in CC 1.2
    ],
    "1.3" => [
      'manifest' => '/templates/cc_1_3/manifest.xml',
      'lti_link' => '/templates/cc_1_3/lti_link.xml',
      'topic' => '/templates/cc_1_3/topic.xml',
      'web_link' => '/templates/cc_1_3/web_link.xml',
      'assignment' => '/templates/cc_1_3/assignment.xml',
    ],
    "thin" => [
      'manifest' => '/templates/thin/manifest.xml',
      'lti_link' => '/templates/cc_1_3/lti_link.xml',
      'topic' => '/templates/cc_1_3/topic.xml', // not actually valid in Thin-CC
      'web_link' => '/templates/cc_1_3/web_link.xml',
      'assignment' => '/templates/cc_1_3/assignment.xml', // not actually valid in Thin-CC
    ],
  ];

  private static $resource_types = [
      "1.1" => [
          'lti_link' => 'imsbasiclti_xmlv1p0',
          'topic' => 'imsdt_xmlv1p1',
          'web_link' => 'imswl_xmlv1p1',
          'assignment' => 'assignment_xmlv1p0',
      ],
      "1.2" => [
          'lti_link' => 'imsbasiclti_xmlv1p0',
          'topic' => 'imsdt_xmlv1p2',
          'web_link' => 'imswl_xmlv1p2',
          'assignment' => 'assignment_xmlv1p0',
      ],
      "1.3" => [
          'lti_link' => 'imsbasiclti_xmlv1p0',
          'topic' => 'imsdt_xmlv1p3',
          'web_link' => 'imswl_xmlv1p3',
          'assignment' => 'assignment_xmlv1p0',
      ],
      "thin" => [
          'lti_link' => 'imsbasiclti_xmlv1p0',
          'topic' => 'imsdt_xmlv1p3',
          'web_link' => 'imswl_xmlv1p3',
          'assignment' => 'assignment_xmlv1p0',
      ],
  ];

  public static $available_options = ['inline', 'include_fm', 'include_bm', 'export_flagged_only',
                                      'use_custom_vars', 'include_parts', 'include_guids',
                                      'include_topics', 'include_assignments', 'use_web_links'];

  public function __construct($structure, $options=[]) {
    $this->book_structure = $structure;
    $this->version = isset($options['version']) ? $options['version'] : 'thin';
    $this->manifest = self::get_manifest_template();
    $this->lti_link_template = $this->get_lti_link_template();
    $this->web_link_template = $this->get_web_link_template();
    $this->topic_template = $this->get_topic_template();
    $this->assignment_template = $this->get_assignment_template();

    if (isset($options['use_page_name_launch_url']) && $options['use_page_name_launch_url']) {
      $this->use_page_name_launch_url = true;
    }
    $this->options = $options;
  }

  public function build_manifest() {
    $this->manifest = str_replace('{course_name}', get_bloginfo('name'), $this->manifest);
    $this->manifest = str_replace('{course_description}', get_bloginfo('description'), $this->manifest);
    $this->manifest = str_replace('{organization_items}', $this->item_parts(), $this->manifest);
    $this->manifest = str_replace('{resources}', $this->item_resources(), $this->manifest);
  }

  public function build_zip() {
    $this->tmp_file = tempnam("tmp","thincc");
    $zip = new \ZipArchive();
    $zip->open($this->tmp_file,\ZipArchive::OVERWRITE);

    $zip->addFromString('imsmanifest.xml', $this->manifest);
    if (!$this->options['inline']) {
      $this->add_resource_files($zip);
    }

    $zip->close();

    return $this->tmp_file;
  }

  public function cleanup() {
    if( isset($this->tmp_file) && get_resource_type($this->tmp_file) === 'file' ){
      unlink($this->tmp_file);
    }
  }

  function __destruct() {
    $this->cleanup();
  }

  private function item_parts() {
    $items = '';
    $template = <<<XML

    <item identifier="%s">
      <title>%s</title>
      %s
    </item>
XML;

    if ($this->options['include_fm']) {
      $fm = $this->book_structure['front-matter'];
      if ($this->item_pages($fm) != '') {
        $items .= sprintf($template, 'frontmatter', 'Front Matter', $this->item_pages($fm));
      }
    }

    foreach ($this->book_structure['part'] as $part) {
      if ($this->item_pages($part) != '') {
        $items .= sprintf($template, $this->identifier($part, "IM_"), $part['post_title'], $this->item_pages($part));
      }
    }

    if ($this->options['include_bm']) {
      $bm = $this->book_structure['back-matter'];
      if ($this->item_pages($bm) != '') {
        $items .= sprintf($template, 'backmatter', 'Back Matter', $this->item_pages($bm));
      }
    }
    return $items;
  }

  private function item_pages($part) {
    $items = '';
    $template = <<<XML

        <item identifier="%s" identifierref="%s">
          <title>%s</title>
        </item>
XML;

    if ($part == $this->book_structure['front-matter'] || $part == $this->book_structure['back-matter']) {
      foreach ($part as $data) {
        $items .= sprintf($template, $this->identifier($data, "I_"), $this->identifier($data), $data['post_title']);
      }
    }
    else {
      // The part link comes first
      if($this->options['include_parts']) {
        $items .= sprintf($template, $this->identifier($part, "I_"), $this->identifier($part), $part['post_title']);
      }

      foreach ($part['chapters'] as $chapter) {
        if ($this->export_page($chapter)) {
          $items .= sprintf($template, $this->identifier($chapter, "I_"), $this->identifier($chapter), $chapter['post_title']);
        }
      }
    }

    return $items;
  }

  private function item_resources() {
    return $this->lti_resources();
  }

  private function get_base_url() {
    $blog_id = get_current_blog_id();
    if($this->options['use_custom_vars']) {
      return get_site_url(1) . '/api/lti/' . $blog_id;
    }
    else if ($this->use_page_name_launch_url) {
      return get_site_url(1) . '/api/lti/' . $blog_id . '?page_title=chapter%%2F%s';
    }
    else {
      return get_site_url(1) . '/api/lti/' . $blog_id . '?page_id=%s';
    }
  }

  private function create_web_url($page) {
      return get_bloginfo('wpurl') . "?p=" .$page['ID'];
  }

  private function create_launch_url($page) {
    if ($this->options['use_custom_vars']) {
      return $this->get_base_url();
    }
    else if ($this->use_page_name_launch_url) {
      return sprintf($this->get_base_url(), $page['post_name']);
    }
    else {
      return sprintf($this->get_base_url(), $page['ID']);
    }
  }

  private function resource_type($page){
    if($this->options['include_topics'] && $this->is_discussion($page)){
      return self::$resource_types[$this->version]['topic'];
    } else if($this->options['include_assignments'] && $this->is_assignment($page)){
      return self::$resource_types[$this->version]['assignment'];
    } else if ($this->options['use_web_links']) {
      return self::$resource_types[$this->version]['web_link'];
    } else {
      return self::$resource_types[$this->version]['lti_link'];
    }
  }

  private function is_discussion($page){
    return (0 === strpos($page['post_title'], 'Discussion'));
  }

  private function is_assignment($page){
    return (0 === strpos($page['post_title'], 'Assignment'));
  }

  private function lti_resources() {
    $resources = '';

    $template = <<<XML

        <resource identifier="%s" type="%s">%s
          %s
        </resource>
XML;

    if ($this->options['include_fm']) {
      foreach ($this->book_structure['front-matter'] as $fm) {
        $resources .= sprintf($template, $this->identifier($fm), $this->resource_type($fm), $this->guid_xml($fm), $this->file_or_link_xml($fm));
      }
    }
    foreach ($this->book_structure['part'] as $part) {
      if ($this->options['include_parts']) {
        $resources .= sprintf($template, $this->identifier($part), $this->resource_type($part), $this->guid_xml($part), $this->file_or_link_xml($part));
      }
      foreach ($part['chapters'] as $chapter) {
        if ($this->export_page($chapter)) {
          $resources .= sprintf($template, $this->identifier($chapter), $this->resource_type($chapter), $this->guid_xml($chapter), $this->file_or_link_xml($chapter));
        }
      }
    }
    if ($this->options['include_bm']) {
      foreach ($this->book_structure['back-matter'] as $bm) {
        $resources .= sprintf($template, $this->identifier($bm), $this->resource_type($bm), $this->guid_xml($bm), $this->file_or_link_xml($bm));
      }
    }
    return $resources;
  }

  // Generates CC GUID metadata based on: http://www.imsglobal.org/cc/ccv1p3/imscc_Implementation-v1p3.html#toc-55
  private function guid_xml($page) {
    if ($this->options['include_guids'] && !empty($this->get_guids($page)) && $this->get_guids($page) !== array("")) {
      $template = <<<XML

            <metadata>
              <curriculumStandardsMetadataSet xmlns="/xsd/imscsmetadata_v1p0">
                <curriculumStandardsMetadata providerId="lumenlearning.com">
                  <setOfGUIDs>%s
                  </setOfGUIDs>
                </curriculumStandardsMetadata>
              </curriculumStandardsMetadataSet>
            </metadata>
XML;
      return sprintf($template, $this->inner_guid_labels_xml($page));
    }
    else {
      return '';
    }
  }

  private function file_or_link_xml($page) {
    if(!$this->options['inline']) {
      return '<file href="' . $this->identifier($page) . '.xml"/>';
    }
    else if($this->options['inline']) {
      return $this->resource_xml($page);
    }
  }

  private function inner_guid_labels_xml($page) {
    $guids = '';
    $guids_array = array($this->get_guids($page));
    $template = <<<XML
                    <labelledGUID>
                      <GUID>%s</GUID>
                    </labelledGUID>
XML;

    if (!empty($guids_array)) {
      foreach ($guids_array as $data) {
        foreach ($data as $guid){
          $guids .= sprintf("\n" . $template, $guid);
        }
      }
      return $guids;
    }
    else {
      return '';
    }
  }

  private function get_guids($page) {
    if ($this->guids_cache === null) {
      global $wpdb;
      $sql = $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", 'CANDELA_OUTCOMES_GUID' );

      foreach ($wpdb->get_results( $sql, ARRAY_A ) as $val) {
        $val['meta_value'] = str_replace(' ', '', $val['meta_value']);
        $this->guids_cache[$val['post_id']] = explode(",", $val['meta_value']);
      }
    }
    return array_key_exists($page['ID'], $this->guids_cache) ? $this->guids_cache[$page['ID']] : [];
  }

  private function add_resource_files($zip) {
    if ($this->options['include_fm']) {
      foreach ($this->book_structure['front-matter'] as $fm) {
        $zip->addFromString($this->identifier($fm) . '.xml', $this->resource_xml($fm, true));
      }
    }
    foreach ($this->book_structure['part'] as $part) {
      if ($this->options['include_parts']) {
        $zip->addFromString($this->identifier($part) . '.xml', $this->resource_xml($part, true));
      }
      foreach ($part['chapters'] as $chapter) {
        if ($this->export_page($chapter)) {
          $zip->addFromString($this->identifier($chapter) . '.xml', $this->resource_xml($chapter, true));
        }
      }
    }
    if ($this->options['include_bm']) {
      foreach ($this->book_structure['back-matter'] as $bm) {
        $zip->addFromString($this->identifier($bm) . '.xml', $this->resource_xml($bm, true));
      }
    }
  }

  private function resource_xml($page, $add_xml_header=false) {
    if($this->options['include_topics'] && $this->is_discussion($page)){
      return $this->topic_xml($page, $add_xml_header);
    } else if($this->options['include_assignments'] && $this->is_assignment($page)){
      return $this->assignment_xml($page, $add_xml_header);
    } else if ($this->options['use_web_links']) {
      return $this->web_link_xml($page, $add_xml_header);
    } else {
      return $this->lti_link_xml($page, $add_xml_header);
    }
  }

  private function web_link_xml($page, $add_xml_header=false) {
    $url = $this->create_web_url($page);
    $template = "\n" . $this->web_link_template;
    if ($add_xml_header) {
      $template = '<?xml version="1.0" encoding="UTF-8"?>' . $template;
    }

    return sprintf($template, $page['post_title'], $url);
  }


  private function lti_link_xml($page, $add_xml_header=false) {
    $launch_url = $this->create_launch_url($page);
    $template = "\n" . $this->lti_link_template;

    if ($add_xml_header) {
      $template = '<?xml version="1.0" encoding="UTF-8"?>' . $template;
    }

    if ($this->options['use_custom_vars']) {
      $custom_variables = '<blti:custom><lticm:property name="page_id">' . $page['ID'] . '</lticm:property></blti:custom>';
    }
    else {
      $custom_variables = '';
    }
    return sprintf($template, $page['post_title'], $launch_url, $custom_variables);
  }

  private function topic_xml($page, $add_xml_header=false) {
    $template = "\n" . $this->topic_template;
    if ($add_xml_header) {
      $template = '<?xml version="1.0" encoding="UTF-8"?>' . $template;
    }

    return sprintf($template, $page['post_title'], htmlspecialchars(get_post_field('post_content', $page['ID']), ENT_XML1));
  }

  private function assignment_xml($page, $add_xml_header=false) {
    $template = "\n" . $this->assignment_template;
    if ($add_xml_header) {
      $template = '<?xml version="1.0" encoding="UTF-8"?>' . $template;
    }

    return sprintf($template, $this->identifier($page), $page['post_title'], htmlspecialchars(get_post_field('post_content', $page['ID']), ENT_XML1));
  }

  private function export_page($page) {
    if ($this->options['export_flagged_only']) {
      return $page['export'] == '1';
    }
    else {
      return true;
    }
  }

  public function __toString() {
    return $this->manifest;
  }

  private function get_manifest_template() {
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['manifest']));
  }

  private function get_lti_link_template() {
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['lti_link']));
  }

  private function get_web_link_template() {
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['web_link']));
  }

  private function get_topic_template() {
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['topic']));
  }

  private function get_assignment_template() {
    return file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . plugin_basename(self::$templates[$this->version]['assignment']));
  }
}
?>
