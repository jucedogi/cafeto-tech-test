<?php

namespace Drupal\cafeto\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Zend\Diactoros\Response\RedirectResponse;

class CafetoController extends ControllerBase {

  const SETTINGS = 'cafeto.settings';

  const BASE_URL = "https://image.tmdb.org/t/p/";

  const POSTER_SIZE = "w780";

  /**
   * Get movies from a certain year from the database.
   *
   * @param int $year
   * @param string $session
   *
   * @return array
   */
  private function _getMovies($year, $session) {
    $data = $this->_queryTMDBAPI('movies', $year);
    $movies = [];
    if (isset($data["data"]["results"])) {
      $no_image
        = \Drupal::request()->getSchemeAndHttpHost()
        . "/"
        . drupal_get_path("module", "cafeto")
        . "/assets/images/no_poster.jpg";

      foreach ($data["data"]["results"] as $movie) {
        if (mb_strlen($movie['poster_path'])) {
          $image = static::BASE_URL . static::POSTER_SIZE . $movie['poster_path'];
        }
        else {
          $image = $no_image;
        }
        $ratin_data = [
          'session' => $session,
          'movie' => $movie['id'],
        ];
        $rating = $this->_queryTMDBAPI('get_rating', $ratin_data);
        $movie_rating = 0;
        if ($rating["data"]["rated"] !== FALSE) {
          $movie_rating = ceil($rating["data"]["rated"]["value"] / 2);
        }
        $movies[] = [
          'id' => $movie['id'],
          'title' => $movie['title'],
          'release_date' => $movie['release_date'],
          'overview' => $movie['overview'],
          'image' => $image,
          'rating' => $movie_rating,
        ];
      }
    }
    return $movies;
  }

  /**
   * Returns false if token aquisition fails or the token if succesfull.
   *
   * @return array
   */
  private function _getToken() {
    $response = [
      'success' => FALSE,
      'token' => FALSE,
    ];
    if (!isset($_COOKIE["Drupal_visitor_tmdb_request_token"])) {
      $token_response = $this->_queryTMDBAPI('token');
      if ($token_response['data']['success']) {
        $cookie_data = ['tmdb_request_token' => $token_response["data"]["request_token"]];
        user_cookie_save($cookie_data);
        $cookie_data = ['tmdb_expires_at' => strtotime($token_response["data"]["expires_at"])];
        user_cookie_save($cookie_data);
        $callback_url
          = \Drupal::request()->getSchemeAndHttpHost()
          . "/cafeto/token-callback";
        $response['success'] = TRUE;
        $response['token'] = $token_response["data"]["request_token"];
        $response['callback'] = $callback_url;
      }
    }
    else {
      $current_time = time();
      $token_expiry_time = (int) $_COOKIE["Drupal_visitor_tmdb_expires_at"];
      if ($token_expiry_time < $current_time) {
        unset($_COOKIE['Drupal_visitor_tmdb_request_token']);
        unset($_COOKIE['Drupal_visitor_tmdb_expires_at']);
        return $this->_getToken();
      }
      else {
        $response['success'] = TRUE;
        $response['token'] = $_COOKIE["Drupal_visitor_tmdb_request_token"];
      }
    }
    return $response;
  }

  public function tmdbTokenAuthCallback(Request $request) {
    $request_token = $request->get('request_token');
    $approved = $request->get('approved');
    if (!is_null($request_token) && !is_null($approved)) {

      return new RedirectResponse("/");
    }
    else {
      $message = $this->t("Token authentication failed. Please try again.");
      $markup = "<p class='alert alert-danger'>{$message}</p>";
      return ['#markup' => $markup];
    }
  }

  /**
   * View to display movies in a carousel.
   *
   * @param $year
   *
   * @return array
   */
  public function movies($year) {
    $movies = $this->_getMovies($year, $this->_getSession()['session']);
    return [
      '#theme' => 'cafeto_slick_carousel',
      '#movies' => $movies,
      '#session' => $this->_getSession()['session'],
      '#attached' => [
        'library' => [
          'cafeto/cafeto.carousel',
        ],
      ],
    ];
  }

  /**
   * Defines custom access for our content.
   *
   * @return \Drupal\Core\Access\AccessResult
   */
  public function access() {
    $access = \Drupal::currentUser()->id() == 1;
    return AccessResult::allowedIf($access);
  }

  /**
   * This is just a method to provide a page in which dev. tests are made.
   */
  public function tests() {
    $data = [];
    $markup = "<pre>" . print_r($data, TRUE) . "</pre>";
    return [
      '#markup' => $markup,
    ];
  }

  public function content() {
    $data = $this->_getToken();
    if (isset($data['callback'])) {
      $url
        = "https://www.themoviedb.org/authenticate/{$data['token']}?redirect_to={$data['callback']}";
      return new RedirectResponse($url);
    }
    return [
      '#theme' => "cafeto_content",
      '#session' => $this->_getSession()['session'],
    ];
  }

  /**
   * Queries to TMDB database are made here.
   *
   * @param string $type
   * @param mixed $data
   *
   * @return array
   *
   * @throws
   */
  private function _queryTMDBAPI($type, $data = NULL) {
    $api_key = $this->config(static::SETTINGS)->get('tmdb_api_key');
    $method = "GET";
    $body = [];
    $response = [
      'success' => FALSE,
      'message' => $this->t('No API key configured.')->render(),
      'data' => [],
    ];
    if (!is_string($api_key) || !mb_strlen($api_key)) {
      return $response;
    }
    else {
      switch ($type) {
        case 'token':
          $request_url = "https://api.themoviedb.org/3/authentication/token/new?api_key={$api_key}";
          break;
        case 'session':
          $method = "POST";
          $body = ["request_token" => $data];
          $request_url
            = "https://api.themoviedb.org/3/authentication/session/new?api_key={$api_key}";
          break;
        case 'movies':
          if (!is_numeric($data)) {
            $year = 2010;
          }
          else {
            $year = $data;
          }
          $request_url
            = "https://api.themoviedb.org/3/discover/movie?api_key={$api_key}&language=en-US&region=US&sort_by=primary_release_date.asc&certification_country=US&certification=PG-13&include_adult=false&include_video=false&primary_release_year={$year}&with_genres=16";
          break;
        case 'clear_rating':
          $method = "DELETE";
          $session = $data['session'];
          $movie = $data['movie'];
          $request_url = "https://api.themoviedb.org/3/movie/{$movie}/rating?api_key={$api_key}&session_id={$session}";
          break;
        case 'set_rating':
          $session = $data['session'];
          $movie = $data['movie'];
          // Value x2 since the database considers values up to 10 and here we do up to 5.
          // Valid values are from 0.5 to 10
          $body = ["value" => ((int) $data['value']) * 2];
          $method = "POST";
          $request_url
            = "https://api.themoviedb.org/3/movie/{$movie}/rating?api_key={$api_key}&session_id={$session}";
          break;
        case 'get_rating':
          $session = $data['session'];
          $movie = $data['movie'];
          $request_url
            = "https://api.themoviedb.org/3/movie/{$movie}/account_states?api_key={$api_key}&session_id={$session}";
          break;
        default:
          $response['message'] = $this->t('Unknown query type.')->render();
          return $response;
      }

      try {
        $client = new Client();
        $request = $client->request($method, $request_url, ["body" => http_build_query($body)]);
        $status_code = $request->getStatusCode();
        if ($status_code === 200 || $status_code === 201) {
          $contents = $request->getBody()->getContents();
          $response['success'] = TRUE;
          $response['message']
            = $this->t('Data obtained successfully.')->render();
          $response['data'] = Json::decode($contents);
          return $response;
        }
        else {
          return [
            'success' => FALSE,
            'message' => $this->t('An error occurred making the API request.')
              ->render(),
            'data' => [],
          ];
        }
      } catch (RequestException $err) {
        \Drupal::logger('cafeto')->error($err->getMessage());
        $response['message']
          = $this->t('An exception occurred making the API request.')->render();
        return $response;
      }
    }
  }

  /**
   * Creates a session on the movie database and returns it.
   * If a session is set in a cookie it will return that existing session.
   *
   * @param string $token
   *
   * @return array
   */
  private function _getSession($token = NULL) {
    $response = [
      'success' => FALSE,
      'session' => FALSE,
    ];
    if (!isset($_COOKIE["Drupal_visitor_tmdb_session"])) {
      if (is_null($token)) {
        $token = $this->_getToken()['token'];
      }
      elseif (!mb_strlen($token)) {
        return $response;
      }
      $session_response = $this->_queryTMDBAPI('session', $token);
      if ($session_response['data']['success']) {
        $cookie_data
          = ['tmdb_session' => $session_response["data"]["session_id"]];
        user_cookie_save($cookie_data);
        $response['success'] = TRUE;
        $response['session'] = $session_response["data"]["session_id"];
      }
    }
    else {
      $response['success'] = TRUE;
      $response['session'] = $_COOKIE["Drupal_visitor_tmdb_session"];
    }
    return $response;
  }

  /*****************************************************************************
   ************************** Custom API methods *******************************
   ****************************************************************************/

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return JsonResponse
   */
  public function setRating(Request $request) {
    $session = $request->get('session');
    $movie = $request->get('movie');
    $value = $request->get('value');
    $data = [
      'session' => $session,
      'movie' => $movie,
      'value' => $value,
    ];
    $result = $this->_queryTMDBAPI('set_rating', $data);
    if (
      isset($result["data"]["status_code"])
      && (
        $result["data"]["status_code"] == 1
        || $result["data"]["status_code"] == 12
      )
    ) {
      return new JsonResponse(['success' => TRUE]);
    }
    else {
      return new JsonResponse(['success' => FALSE]);
    }
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return JsonResponse
   */
  public function deleteRating(Request $request) {
    $session = $request->get('session');
    $movie = $request->get('movie');
    $data = [
      'session' => $session,
      'movie' => $movie,
    ];
    $result = $this->_queryTMDBAPI('clear_rating', $data);
    if (isset($result["data"]["status_code"]) && $result["data"]["status_code"] == 13) {
      return new JsonResponse(['success' => TRUE]);
    }
    else {
      return new JsonResponse(['success' => FALSE]);
    }
  }

  /**
   * Get a movie database session.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function getSession() {
    return new JsonResponse($this->_getSession());
  }

  /**
   * Get movies from a certain year.
   *
   * @param $year
   * @param $session
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function getMovies($year, $session) {
    $movies = $this->_getMovies($year, $session);
    return new JsonResponse($movies);
  }
}
