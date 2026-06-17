<?php

namespace Drupal\oit\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for abuse IP actions.
 */
class AbuseConfirmForm extends ConfirmFormBase {

  const ACTION_KEEP = 1;
  const ACTION_NOKEEP = 2;
  const ACTION_WHITELIST = 3;

  /**
   * The IP address being acted upon.
   *
   * @var string
   */
  protected $ip;

  /**
   * The action to perform.
   *
   * @var int
   */
  protected $action;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs an AbuseConfirmForm.
   */
  public function __construct(
    StateInterface $state,
    Connection $database,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->state = $state;
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('state'),
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oit_abuse_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('oit.abusetable');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to @action IP address @ip?', [
      '@action' => $this->actionVerb(),
      '@ip' => $this->ip,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $ip = NULL, $action = NULL) {
    $this->ip = urldecode(Xss::filter((string) $ip));
    $this->action = (int) $action;

    if (!filter_var($this->ip, FILTER_VALIDATE_IP)) {
      $this->messenger()->addError($this->t('Invalid IP address: @ip', ['@ip' => $this->ip]));
      return $this->redirect('oit.abusetable');
    }

    if (!in_array($this->action, [self::ACTION_KEEP, self::ACTION_NOKEEP, self::ACTION_WHITELIST], TRUE)) {
      $this->messenger()->addError($this->t('Invalid action.'));
      return $this->redirect('oit.abusetable');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->action >= self::ACTION_KEEP) {
      $this->abuseIpRemove($this->ip);
    }
    if ($this->action >= self::ACTION_NOKEEP) {
      $this->banIpRemove($this->ip);
    }
    if ($this->action >= self::ACTION_WHITELIST) {
      $this->ipWhitelist($this->ip);
    }

    $messages = [
      self::ACTION_KEEP => 'kept banned',
      self::ACTION_NOKEEP => 'removed from the ban list',
      self::ACTION_WHITELIST => 'whitelisted',
    ];
    $this->loggerFactory->get('oit')->info('IP address @ip has been @action.', [
      '@ip' => $this->ip,
      '@action' => $messages[$this->action],
    ]);
    $this->messenger()->addMessage($this->t('IP address @ip has been @action.', [
      '@ip' => $this->ip,
      '@action' => $messages[$this->action],
    ]));

    $form_state->setRedirect('oit.abusetable');
  }

  /**
   * Remove IP from the questionable-ban state list.
   *
   * @param string $ip
   *   The IP address to remove.
   */
  private function abuseIpRemove($ip) {
    $abuse = json_decode($this->state->get('ban_ip_questionable'), TRUE) ?: [];
    if (isset($abuse[$ip])) {
      unset($abuse[$ip]);
      $this->state->set('ban_ip_questionable', json_encode($abuse));
    }
  }

  /**
   * Remove IP from the advban_ip table.
   *
   * @param string $ip
   *   The IP address to remove.
   */
  private function banIpRemove($ip) {
    $this->database->delete('advban_ip')
      ->condition('ip', $ip)
      ->execute();
  }

  /**
   * Append IP to the autoban whitelist config.
   *
   * @param string $ip
   *   The IP address to whitelist.
   */
  private function ipWhitelist($ip) {
    $autoban_settings = $this->configFactory->getEditable('autoban.settings');
    $whitelist_raw = $autoban_settings->get('autoban_whitelist') ?? '';
    $entries = array_filter(array_map('trim', explode("\n", $whitelist_raw)));
    if (!in_array($ip, $entries, TRUE)) {
      $entries[] = $ip;
      $autoban_settings->set('autoban_whitelist', implode("\n", $entries))->save();
    }
  }

  /**
   * Human-readable verb for the pending action.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated verb for the action.
   */
  private function actionVerb() {
    return match ($this->action) {
      self::ACTION_KEEP => $this->t('keep banned'),
      self::ACTION_NOKEEP => $this->t('remove the ban on'),
      self::ACTION_WHITELIST => $this->t('whitelist'),
      default => $this->t('act on'),
    };
  }

}
