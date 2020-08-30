<?php

trait OmbiHomepageItem
{
	public function testConnectionOmbi()
	{
		if (empty($this->config['ombiURL'])) {
			$this->setAPIResponse('error', 'Ombi URL is not defined', 422);
			return false;
		}
		if (empty($this->config['ombiToken'])) {
			$this->setAPIResponse('error', 'Ombi Token is not defined', 422);
			return false;
		}
		$headers = array(
			"Accept" => "application/json",
			"Apikey" => $this->config['ombiToken'],
		);
		$url = $this->qualifyURL($this->config['ombiURL']);
		try {
			$options = ($this->localURL($url)) ? array('verify' => false) : array();
			$test = Requests::get($url . "/api/v1/Settings/about", $headers, $options);
			if ($test->success) {
				$this->setAPIResponse('success', 'API Connection succeeded', 200);
				return true;
			}
			
		} catch (Requests_Exception $e) {
			$this->writeLog('error', 'OMBI Connect Function - Error: ' . $e->getMessage(), 'SYSTEM');
			$this->setAPIResponse('error', $e->getMessage(), 500);
			return false;
		};
	}
	
	public function ombiTVDefault($type)
	{
		return $type == $this->config['ombiTvDefault'];
	}
	
	public function getOmbiRequests($type = "both", $limit = 50, $offset = 0)
	{
		if (!$this->config['homepageOmbiEnabled']) {
			$this->setAPIResponse('error', 'Ombi homepage item is not enabled', 409);
			return false;
		}
		if (!$this->qualifyRequest($this->config['homepageOmbiAuth'])) {
			$this->setAPIResponse('error', 'User not approved to view this homepage item', 401);
			return false;
		}
		if (empty($this->config['ombiURL'])) {
			$this->setAPIResponse('error', 'Ombi URL is not defined', 422);
			return false;
		}
		if (empty($this->config['ombiToken'])) {
			$this->setAPIResponse('error', 'Ombi Token is not defined', 422);
			return false;
		}
		$api['count'] = array(
			'movie' => 0,
			'tv' => 0,
			'limit' => (integer)$limit,
			'offset' => (integer)$offset
		);
		$headers = array(
			"Accept" => "application/json",
			"Apikey" => $this->config['ombiToken'],
		);
		$requests = array();
		$url = $this->qualifyURL($this->config['ombiURL']);
		try {
			$options = ($this->localURL($url)) ? array('verify' => false) : array();
			switch ($type) {
				case 'movie':
					$movie = Requests::get($url . "/api/v1/Request/movie", $headers, $options);
					break;
				case 'tv':
					$tv = Requests::get($url . "/api/v1/Request/tv", $headers, $options);
					break;
				default:
					$movie = Requests::get($url . "/api/v1/Request/movie", $headers, $options);
					$tv = Requests::get($url . "/api/v1/Request/tv", $headers, $options);
					break;
			}
			if ($movie->success || $tv->success) {
				if (isset($movie)) {
					$movie = json_decode($movie->body, true);
					//$movie = array_reverse($movie);
					foreach ($movie as $key => $value) {
						$proceed = (($this->config['ombiLimitUser']) && strtolower($this->user['username']) == strtolower($value['requestedUser']['userName'])) || (!$this->config['ombiLimitUser']) || $this->qualifyRequest(1);
						if ($proceed) {
							$api['count']['movie']++;
							$requests[] = array(
								'id' => $value['theMovieDbId'],
								'title' => $value['title'],
								'overview' => $value['overview'],
								'poster' => (isset($value['posterPath']) && $value['posterPath'] !== '') ? 'https://image.tmdb.org/t/p/w300/' . $value['posterPath'] : 'plugins/images/cache/no-list.png',
								'background' => (isset($value['background']) && $value['background'] !== '') ? 'https://image.tmdb.org/t/p/w1280/' . $value['background'] : '',
								'approved' => $value['approved'],
								'available' => $value['available'],
								'denied' => $value['denied'],
								'deniedReason' => $value['deniedReason'],
								'user' => $value['requestedUser']['userName'],
								'userAlias' => $value['requestedUser']['userAlias'],
								'request_id' => $value['id'],
								'request_date' => $value['requestedDate'],
								'release_date' => $value['releaseDate'],
								'type' => 'movie',
								'icon' => 'mdi mdi-filmstrip',
								'color' => 'palette-Deep-Purple-900 bg white',
							);
						}
					}
				}
				if (isset($tv) && (is_array($tv) || is_object($tv))) {
					$tv = json_decode($tv->body, true);
					foreach ($tv as $key => $value) {
						if (count($value['childRequests']) > 0) {
							$proceed = (($this->config['ombiLimitUser']) && strtolower($this->user['username']) == strtolower($value['childRequests'][0]['requestedUser']['userName'])) || (!$this->config['ombiLimitUser']) || $this->qualifyRequest(1);
							if ($proceed) {
								$api['count']['tv']++;
								$requests[] = array(
									'id' => $value['tvDbId'],
									'title' => $value['title'],
									'overview' => $value['overview'],
									'poster' => (isset($value['posterPath']) && $value['posterPath'] !== '') ? $value['posterPath'] : 'plugins/images/cache/no-list.png',
									'background' => (isset($value['background']) && $value['background'] !== '') ? 'https://image.tmdb.org/t/p/w1280/' . $value['background'] : '',
									'approved' => $value['childRequests'][0]['approved'],
									'available' => $value['childRequests'][0]['available'],
									'denied' => $value['childRequests'][0]['denied'],
									'deniedReason' => $value['childRequests'][0]['deniedReason'],
									'user' => $value['childRequests'][0]['requestedUser']['userName'],
									'userAlias' => $value['childRequests'][0]['requestedUser']['userAlias'],
									'request_id' => $value['id'],
									'request_date' => $value['childRequests'][0]['requestedDate'],
									'release_date' => $value['releaseDate'],
									'type' => 'tv',
									'icon' => 'mdi mdi-television',
									'color' => 'grayish-blue-bg',
								);
							}
						}
					}
				}
				//sort here
				usort($requests, function ($item1, $item2) {
					if ($item1['request_date'] == $item2['request_date']) {
						return 0;
					}
					return $item1['request_date'] > $item2['request_date'] ? -1 : 1;
				});
			}
		} catch (Requests_Exception $e) {
			$this->writeLog('error', 'OMBI Connect Function - Error: ' . $e->getMessage(), 'SYSTEM');
			$this->setAPIResponse('error', $e->getMessage(), 500);
			return false;
		};
		$api['content'] = isset($requests) ? array_slice($requests, $offset, $limit) : false;
		$this->setAPIResponse('success', null, 200, $api);
		return $api;
	}
	
	public function addOmbiRequest($id, $type)
	{
		$id = ($id) ?? null;
		$type = ($type) ?? null;
		if (!$id) {
			$this->setAPIResponse('error', 'Id was not supplied', 422);
			return false;
		}
		if (!$type) {
			$this->setAPIResponse('error', 'Type was not supplied', 422);
			return false;
		}
		if (!$this->config['homepageOmbiEnabled']) {
			$this->setAPIResponse('error', 'Ombi homepage item is not enabled', 409);
			return false;
		}
		if (!$this->qualifyRequest($this->config['homepageOmbiAuth'])) {
			$this->setAPIResponse('error', 'User not approved to view this homepage item', 401);
			return false;
		}
		if (empty($this->config['ombiURL'])) {
			$this->setAPIResponse('error', 'Ombi URL is not defined', 422);
			return false;
		}
		if (empty($this->config['ombiToken'])) {
			$this->setAPIResponse('error', 'Ombi Token is not defined', 422);
			return false;
		}
		$url = $this->qualifyURL($this->config['ombiURL']);
		switch ($type) {
			case 'season':
			case 'tv':
				$type = 'tv';
				$add = array(
					'tvDbId' => $id,
					'requestAll' => $this->ombiTVDefault('all'),
					'latestSeason' => $this->ombiTVDefault('last'),
					'firstSeason' => $this->ombiTVDefault('first')
				);
				break;
			default:
				$type = 'movie';
				$add = array("theMovieDbId" => (int)$id);
				break;
		}
		try {
			$options = ($this->localURL($url)) ? array('verify' => false, 'timeout' => 30) : array('timeout' => 30);
			if (isset($_COOKIE['Auth'])) {
				$headers = array(
					"Accept" => "application/json",
					"Content-Type" => "application/json",
					"Authorization" => "Bearer " . $_COOKIE['Auth']
				);
			} else {
				$this->setAPIResponse('error', 'User does not have Auth Cookie', 500);
				return false;
			}
			//https://api.themoviedb.org/3/movie/157336?api_key=83cf4ee97bb728eeaf9d4a54e64356a1
			// Lets check if it exists inside Ombi first... but since I can't search with ID - i have to query title from id
			$tmdbResponse = Requests::get('https://api.themoviedb.org/3/' . $type . '/' . $id . '?api_key=83cf4ee97bb728eeaf9d4a54e64356a1', [], $options);
			if ($tmdbResponse->success) {
				$details = json_decode($tmdbResponse->body, true);
				if (count($details) > 0) {
					switch ($type) {
						case 'tv':
							$title = $details['name'];
							$idType = 'theTvDbId';
							$tmdbResponseID = Requests::get('https://api.themoviedb.org/3/tv/' . $id . '/external_ids?api_key=83cf4ee97bb728eeaf9d4a54e64356a1', [], $options);
							if ($tmdbResponseID->success) {
								$detailsID = json_decode($tmdbResponseID->body, true);
								if (count($detailsID) > 0) {
									if (isset($detailsID['tvdb_id'])) {
										$id = $detailsID['tvdb_id'];
										$add['tvDbId'] = $id;
									} else {
										$this->setAPIResponse('error', 'Could not get TVDB Id', 422);
										return false;
									}
								} else {
									$this->setAPIResponse('error', 'Could not get TVDB Id', 422);
									return false;
								}
							}
							break;
						case 'movie':
							$title = $details['title'];
							$idType = 'theMovieDbId';
							break;
						default:
							$this->setAPIResponse('error', 'Ombi Type was not found', 422);
							return false;
					}
				} else {
					$this->setAPIResponse('error', 'No data returned from TMDB', 422);
					return false;
				}
			} else {
				$this->setAPIResponse('error', 'Could not contact TMDB', 422);
				return false;
			}
			$searchResponse = Requests::get($url . '/api/v1/Search/' . $type . '/' . urlencode($title), $headers, $options);
			if ($searchResponse->success) {
				$details = json_decode($searchResponse->body, true);
				if (count($details) > 0) {
					foreach ($details as $k => $v) {
						if ($v[$idType] == $id) {
							if ($v['available']) {
								$this->setAPIResponse('error', 'Request is already available', 409);
								return false;
							} elseif ($v['requested']) {
								$this->setAPIResponse('error', 'Request is already requested', 409);
								return false;
							}
						}
					}
				}
			} else {
				$this->setAPIResponse('error', 'Ombi Error Occurred', 500);
				return false;
			}
			$response = Requests::post($url . "/api/v1/Request/" . $type, $headers, json_encode($add), $options);
			if ($response->success) {
				$this->setAPIResponse('success', 'Ombi Request submitted', 200);
				return true;
			} else {
				$this->setAPIResponse('error', 'Ombi Error Occurred', 500);
				return false;
			}
			
		} catch (Requests_Exception $e) {
			$this->writeLog('error', 'OMBI Connect Function - Error: ' . $e->getMessage(), 'SYSTEM');
			$this->setAPIResponse('error', $e->getMessage(), 500);
			return false;
		}
	}
	
	public function actionOmbiRequest($id, $type, $action)
	{
		$id = ($id) ?? null;
		$type = ($type) ?? null;
		$action = ($action) ?? null;
		if (!$id) {
			$this->setAPIResponse('error', 'Id was not supplied', 422);
			return false;
		}
		if (!$type) {
			$this->setAPIResponse('error', 'Type was not supplied', 422);
			return false;
		}
		if (!$action) {
			$this->setAPIResponse('error', 'Action was not supplied', 422);
			return false;
		}
		if (!$this->config['homepageOmbiEnabled']) {
			$this->setAPIResponse('error', 'Ombi homepage item is not enabled', 409);
			return false;
		}
		if (!$this->qualifyRequest($this->config['homepageOmbiAuth'])) {
			$this->setAPIResponse('error', 'User not approved to view this homepage item', 401);
			return false;
		}
		if (!$this->qualifyRequest(1)) {
			$this->setAPIResponse('error', 'User must be an admin', 401);
			return false;
		}
		if (empty($this->config['ombiURL'])) {
			$this->setAPIResponse('error', 'Ombi URL is not defined', 422);
			return false;
		}
		if (empty($this->config['ombiToken'])) {
			$this->setAPIResponse('error', 'Ombi Token is not defined', 422);
			return false;
		}
		$url = $this->qualifyURL($this->config['ombiURL']);
		$headers = array(
			"Accept" => "application/json",
			"Content-Type" => "application/json",
			"Apikey" => $this->config['ombiToken']
		);
		$data = array(
			'id' => $id,
		);
		switch ($type) {
			case 'season':
			case 'tv':
				$type = 'tv';
				break;
			default:
				$type = 'movie';
				break;
		}
		try {
			$options = ($this->localURL($url)) ? array('verify' => false, 'timeout' => 30) : array('timeout' => 30);
			switch ($action) {
				case 'approve':
					$response = Requests::post($url . "/api/v1/Request/" . $type . "/approve", $headers, json_encode($data), $options);
					$message = 'Ombi Request has been approved';
					break;
				case 'available':
					$response = Requests::post($url . "/api/v1/Request/" . $type . "/available", $headers, json_encode($data), $options);
					$message = 'Ombi Request has been marked available';
					break;
				case 'unavailable':
					$response = Requests::post($url . "/api/v1/Request/" . $type . "/unavailable", $headers, json_encode($data), $options);
					$message = 'Ombi Request has been marked unavailable';
					break;
				case 'deny':
					$response = Requests::put($url . "/api/v1/Request/" . $type . "/deny", $headers, json_encode($data), $options);
					$message = 'Ombi Request has been denied';
					break;
				case 'delete':
					$response = Requests::delete($url . "/api/v1/Request/" . $type . "/" . $id, $headers, $options);
					$message = 'Ombi Request has been deleted';
					break;
				default:
					return false;
			}
			if ($response->success) {
				$this->setAPIResponse('success', $message, 200);
				return true;
			} else {
				$this->setAPIResponse('error', 'Ombi Error Occurred', 500);
				return false;
			}
		} catch (Requests_Exception $e) {
			$this->writeLog('error', 'OMBI Connect Function - Error: ' . $e->getMessage(), 'SYSTEM');
			$this->setAPIResponse('error', $e->getMessage(), 500);
			return false;
		};
	}
}