<?php

namespace Drupal\felipe_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;

/**
 * Provides route responses for the Felipe API module.
 */
class FelipeApiController extends ControllerBase {

  /**
   * Callback function for the "get-flight-options" endpoint.
   */
  public function getFlightOptions(Request $request) {
    $marketCode = $request->query->get('marketCode');
    $regionCode = $request->query->get('regionCode');
    $language = $request->query->get('language');
    $status = $request->query->get('status');

    // Validar los parámetros y realizar la lógica de filtrado del contenido.
    if (empty($marketCode) || empty($regionCode) || empty($language) || empty($status)) {
      // Devolver error 400 Bad Request si falta algún parámetro.
      return new JsonResponse(['error' => 'Missing parameters'], 400);
    }

    // Filtrar el contenido según los parámetros recibidos.
    $filteredContent = $this->filterContent($marketCode, $regionCode, $language, $status);

    if (empty($filteredContent)) {
      // Devolver error 404 Not Found si no se encuentra contenido.
      return new JsonResponse(['error' => 'Content not found'], 404);
    }

    // Construir la respuesta en formato JSON.
    $response = [
      'title' => $filteredContent['title'],
      'icon' => $filteredContent['icon'],
      'marketCode' => $marketCode,
      'regionCode' => $regionCode,
      'isRecommend' => $filteredContent['isRecommend'],
      'airportCode' => $filteredContent['airportCode'],
      'benefits' => $filteredContent['benefits'],
      'tags' => $filteredContent['tags'],
      'currency' => $filteredContent['currency'],
    ];

    $response = new JsonResponse($response);
    $response->setMaxAge(900);

    return $response;
  }

  /**
   * Filtra el contenido según los parámetros recibidos.
   */
  private function filterContent($marketCode, $regionCode, $language, $status) {

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'flight_options')
      ->condition('field_market_code', $marketCode)
      ->condition('field_region_code', $regionCode);

    if ($status == 1) {
      $query->condition('status', 1);
    } elseif ($status == 0) {
      $query->condition('status', 0);
    }

    $entity_ids = $query->execute();

    $filteredContent = [];

    if (!empty($entity_ids)) {
      foreach ($entity_ids as $entity_id) {
        $entity = \Drupal::entityTypeManager()->getStorage('node')->load($entity_id);

        // Obtener los valores de los campos necesarios.
        $title = $entity->getTitle();
        $icon = $entity->get('field_icono')->entity->getFileUri();
        $isRecommend = $entity->get('field_is_recommend')->value;
        $airportCode = [];
        $benefits = [];
        $tags = $entity->get('field_m01_tags')->value;
        $currency = $entity->get('field_currency')->entity->label();

        // Obtener los valores del campo de referencia a aeropuertos.
        $airportCodeReferences = $entity->get('field_airportcode')->referencedEntities();
        foreach ($airportCodeReferences as $airportCodeRef) {
          $airport = [
            'title' => $airportCodeRef->getTitle(),
            'icon' => $airportCodeRef->get('field_icon')->entity->getFileUri(),
            'city' => $airportCodeRef->get('field_city')->value,
            'code' => $airportCodeRef->get('field_code')->value,
            'wifi' => $airportCodeRef->get('field_wifi')->value,
          ];
          $airportCode[] = $airport;
        }

        // Obtener los valores del campo de referencia a beneficios.
        $benefitsReferences = $entity->get('field_benefits')->referencedEntities();
        foreach ($benefitsReferences as $benefitRef) {
          $benefit = [
            'title' => $benefitRef->getTitle(),
            'subTitle' => $benefitRef->get('field_subtitle')->value,
            'isAvalible' => $benefitRef->get('field_is_available')->value,
            'image' => $benefitRef->get('field_imagen')->entity->getFileUri(),
            'toolTipText' => $benefitRef->get('field_tooltiptext')->value,
          ];
          $benefits[] = $benefit;
        }

        $filteredContent = [
          'title' => $title,
          'icon' => $icon,
          'isRecommend' => $isRecommend,
          'airportCode' => $airportCode,
          'benefits' => $benefits,
          'tags' => $tags,
          'currency' => $currency,
        ];
      }
    }

    return $filteredContent;
  }
}

