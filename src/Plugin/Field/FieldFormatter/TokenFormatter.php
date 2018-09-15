<?php

namespace Drupal\token_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Utility\Token;
use Drupal\token\TokenEntityMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for TokenFormatter.
 *
 * @FieldFormatter(
 *   id = "token_formatter",
 *   label = @Translation("Tokenized text"),
 *   description = @Translation("Display tokenized text as an optional link with tokenized destination."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class TokenFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The token entity mapper.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $tokenEntityMapper;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

/**
   * Constructs a TokenFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\token\TokenEntityMapperInterface $token_entity_mapper
   *   The token entity mapper.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, TokenEntityMapperInterface $token_entity_mapper, Token $token_service) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->tokenEntityMapper = $token_entity_mapper;
    $this->tokenService = $token_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('token.entity_mapper'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'text' => '[entity:label]',
      'link' => '[entity:url]',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $settings = $this->getSettings();
    $entity_type = $this->getFieldSetting('target_type');
    $token_type = \Drupal::service('token.entity_mapper')->getTokenTypeForEntityType($entity_type);  //$this->tokenEntityMapper

    $element['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text to output'),
      '#description' => $this->t('The text to display for this field. You may include HTML. This field accepts tokens.'),
      '#default_value' => $settings['text'],
      '#element_validate' => ['token_element_validate'],
      '#token_types' => [$token_type, 'entity'],
      '#required' => TRUE,
    ];

    $element['link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link destination'),
      '#description' => $this->t('Leave blank to output the text only not as a link. This field accepts tokens.'),
      '#default_value' => $settings['link'],
      '#element_validate' => ['token_element_validate'],
      '#token_types' => [$token_type, 'entity'],
    ];

    $element['tokens'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => [$token_type, 'entity'],
      '#global_types' => FALSE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();

    $summary[] = $this->t('Text: %text', ['%text' => trim($settings['text'])]);
    if ($link = trim($settings['link'])) {
      $summary[] = $this->t('Linked to: %link', ['%link' => trim($settings['link'])]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $settings = $this->getSettings();
    $entity_type = $this->getFieldSetting('target_type');
    $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity_type);

    $token_options = [
      'sanitize' => TRUE,
      'clear' => TRUE,
    ];

    foreach ($items->referencedEntities() as $delta => $item) {
      $token_data = [
        $token_type => $item,
        'entity' => $item,
      ];
      $text = $this->tokenService->replace(trim($settings['text']), $token_data, $token_options);
      if ($link = $this->tokenService->replace(trim($settings['link']), $token_data, $token_options)) {
        $linkObj = Link::fromTextAndUrl(Markup::create($text), Url::fromUri($link));
        $element[$delta] = $linkObj->toRenderable();
      }
      else {
        $element[$delta] = ['#markup' => $text];
      }
    }

    return $element;
  }

}
