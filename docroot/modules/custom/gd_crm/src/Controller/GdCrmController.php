<?php

namespace Drupal\gd_crm\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\webform\Entity\WebformSubmission;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for GD CRM Integration routes.
 */
class GdCrmController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * Builds the response.
   */
  public function build() {
    // @todo: thin controller.
    // @todo: use dependency injection.
    $configs = Drupal::configFactory()->get('gd_crm.settings');
    $client = Drupal::httpClient();

    $endpoint = $configs->get('crm_file_upload_url');
    $token = $configs->get('crm_auth_token');
    $file_upload_requests = json_decode(Drupal::request()->getContent());


    if (!$this->validateRequestParams($file_upload_requests)) {
      return new Response($this->t('Request format is invalid.'), 400);
    }

    foreach ($file_upload_requests as $file_upload_request) {
      // @todo: get request ID from proper source when documentation is updated.
      $request_id = Drupal::request()->query->get('request_id');
      $entity_name = $file_upload_request->entityName;
      $entity_id = $file_upload_request->id;

      $endpoint .= '/' . $entity_name . '/' . $entity_id;

      list($invoice_copy, $defect_photo) = $this->getSubmissionData($request_id);

      if (!$invoice_copy) {
        return new Response($this->t('No submission found for request: ' . $request_id), 400);
      }

      $invoice_request_options = [
        'headers' => [
          'x-functions-key' => $token,
        ],
        'body' => $invoice_copy,
      ];

      $defect_request_options = [
        'headers' => [
          'x-functions-key' => $token,
        ],
        'body' => $defect_photo,
      ];

      try {
        $client->post($endpoint, $invoice_request_options);
        $client->post($endpoint, $defect_request_options);
      }
      catch (ClientException $e) {
        Drupal::logger('crm_integration')->error(t('Unable to send submission. Please try again later.'));
        return new Response($this->t('There was a problem submitting file to CRM.'), 400);
      }

    }

    return new Response($this->t('Files have been sent to CRM.'), 200);
  }

  private function getSubmissionData($request_id) {
    $query = Drupal::database()->select('webform_submission_data', 's');
    $query->addField('s', 'sid', 'sid');
    $query->condition('s.name', 'requestId');
    $query->condition('s.value', $request_id);
    $res = $query->execute()->fetchField();

    $res = WebformSubmission::load($res);
    $file_id = $res->getElementData('proofOfPurchase');
    $file = File::load($file_id);
    $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $invoice_copy = file_get_contents($file_path);

    $res = WebformSubmission::load($res);
    $file_id = $res->getElementData('photoOfIssue');
    $file = File::load($file_id);
    $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $defect_photo = file_get_contents($file_path);


    return [$invoice_copy, $defect_photo];
  }

  private function validateRequestParams($params) {
    // Validating request.
    if (!$params || !is_array($params)) {
      return FALSE;
    }

    foreach ($params as $param) {
      if (!isset($param->entityName) || !isset($param->id)) {
        return FALSE;
      }
    }
  }

}
