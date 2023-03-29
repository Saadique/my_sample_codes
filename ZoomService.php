<?php

namespace App\Services\Zoom;

use App\Repositories\V1_1\Catalog\Provider\O2OProviderInterface;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use GuzzleHttp\Client;
use RuntimeException;

class ZoomService implements ZoomServiceInterface
{
    private $client;
    private $provider;
    private $client_key;
    private $client_secret;
    private $CALL_BACK_URL;

    public function __construct(O2OProviderInterface $provider)
    {
        $this->CALL_BACK_URL = config('app.url')."/api/organizer/zoom/callback?state=";
        $this->client = new Client(['base_uri' => 'https://api.zoom.us']);
        $this->provider = $provider;
        $this->client_key = "nPX7FotNQRSzdnhBsCYyg";
        $this->client_secret = "QkNDhzkfmxF0XOMddEEqQUaw5KF5VpQj";
    }

    public function authorize($account_id)
    {
        $call_back_url = $this->CALL_BACK_URL."$account_id";
        $oauth_url = "/oauth/authorize?response_type=code&client_id=$this->client_key&redirect_uri=$call_back_url";

        try {
            $oauth_url = "https://api.zoom.us".$oauth_url;
            return $oauth_url;
        } catch (\Exception $e) {
            Bugsnag::notifyException(new RuntimeException($e));
        }
    }


    public function getAccessToken($code, $account_id)
    {
        $call_back_url = $this->CALL_BACK_URL.$account_id;
        try {
            $response = $this->client->request('POST', '/oauth/token', [
                "headers" => [
                    "Authorization" => "Basic ". base64_encode($this->client_key.':'.$this->client_secret)
                ],
                'form_params' => [
                    "grant_type" => "authorization_code",
                    "code" => $code,
                    "redirect_uri" => "https://app.dev.cloud.digitalmediasolutions.com.au/api/organizer/zoom/callback?state=$account_id"
                ],
            ]);
            $tokens = json_decode($response->getBody()->getContents());

            $provider = $this->provider->configData("zoom", $account_id, []);
            $this->saveToken($tokens->access_token, $tokens->refresh_token, $provider, $account_id);
        } catch (\Exception $e) {
            dd($e->getMessage());
            Bugsnag::notifyException(new RuntimeException($e));
        }
    }

    public function refreshToken($provider)
    {
        try {
            $refresh_token = $this->retrieveRefreshToken($provider);
            $response = $this->client->request('POST', '/oauth/token', [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->client_key.':'.$this->client_secret),
                ],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refresh_token,
                ],
            ]);
            $response = json_decode($response->getBody()->getContents());
            $this->saveToken($response->access_token, $response->refresh_token, $provider, $provider->config['org_id']);
        } catch (\Exception $exception) {
            Bugsnag::notifyException(new RuntimeException($exception));
        }
    }

    public function createMeeting($provider, $meeting, $email)
    {
        try {
            $access_token = $this->retrieveAccessToken($provider);
            $response = $this->client->request(
                'POST',
                "/v2/users/sadiqzufar@gmail.com/meetings",
                ["headers" =>
                    ["Authorization" => "Bearer $access_token"],
                    'json' => [
                        "topic" => $meeting->subject,
                        "type" => 2,
                        "start_time" => $meeting->starts_at,
                        "duration" => meetingDuration($meeting->duration, false),
                        "timezone" => 'UTC',
                        "schedule_for"=> "sadiqzufar@gmail.com",
                        "default_password" => false,
                        "settings" => [
                            "join_before_host" => true,
                            "approval_type" => 1,
                            "registration_type" => 2,
                            "enforce_login" => false,
                            "waiting_room" => false,
                        ]
                    ]
                ]
            );
            $responseBody = json_decode($response->getBody());
            return $responseBody;
        } catch (\Exception $exception) {
            if ($exception->getCode() == 401) {
                $this->refreshToken($provider);
                $this->createMeeting($provider, $meeting, $email);
            } else {
                Bugsnag::notifyException(new RuntimeException($exception));
            }
        }
    }

    public function updateMeeting($provider, $meeting, $zoom_meeting_id)
    {
        try {
            $access_token = $this->retrieveAccessToken($provider);
            $response = $this->client->request(
                'PATCH',
                "/v2/meetings/$zoom_meeting_id",
                ["headers" =>
                    ["Authorization" => "Bearer $access_token"],
                    'json' => [
                        "topic" => $meeting->subject,
                        "type" => 2,
                        "start_time" => $meeting->starts_at,
                        "duration" => meetingDuration($meeting->duration, false),
                    ]
                ]
            );

            $responseBody = json_decode($response->getBody());
            return $responseBody;
        } catch (\Exception $exception) {
            if ($exception->getCode() == 401) {
                $this->refreshToken($provider);
                $this->updateMeeting($provider, $meeting, $zoom_meeting_id);
            } else {
                Bugsnag::notifyException(new RuntimeException($exception));
            }
        }
    }

    public function deleteMeeting($provider, $zoom_meeting_id)
    {
        try {
            $access_token = $this->retrieveAccessToken($provider);
            $response = $this->client->request(
                'DELETE',
                "/v2/meetings/$zoom_meeting_id",
                ["headers" =>
                    ["Authorization" => "Bearer $access_token"],
                ]
            );

            $responseBody = json_decode($response->getBody());
            return $responseBody;
        } catch (\Exception $exception) {
            if ($exception->getCode() == 401) {
                $this->refreshToken($provider);
                $this->deleteMeeting($provider, $zoom_meeting_id);
            } else {
                Bugsnag::notifyException(new RuntimeException($exception));
            }
        }
    }

    public function getMeetingInvitation($zoom_meeting_id, $provider)
    {
        try {
            $access_token = $this->retrieveAccessToken($provider);
            $response = $this->client->request(
                'GET',
                "/v2/meetings/$zoom_meeting_id/invitation",
                ["headers" =>
                    ["Authorization" => "Bearer $access_token"],
                ]
            );

            $responseBody = json_decode($response->getBody());
            return $responseBody;
        } catch (\Exception $exception) {
            if ($exception->getCode() == 401) {
                $this->refreshToken($provider);
                $this->getMeetingInvitation($zoom_meeting_id, $provider);
            } else {
                Bugsnag::notifyException(new RuntimeException($exception));
            }
        }
    }

    public function createZoomUser($provider, $email)
    {
        try {
            $access_token = $this->retrieveAccessToken($provider);
            $response = $this->client->request(
                'POST',
                '/v2/users',
                ["headers" =>
                    ["Authorization" => "Bearer $access_token"],
                    'json' => [
                        "action" => "custCreate",
                        "user_info" => [
                            "email" => "sadiqzufar@gmail.com",
                            "type"  => 1
                        ]
                    ]
                ]
            );

            $responseBody = json_decode($response->getBody());
            return true;
        } catch (\Exception $exception) {
            if ($exception->getCode() == 401) {
                $this->refreshToken($provider);
                $this->createZoomUser($provider, $email);
            } else {
                Bugsnag::notifyException(new RuntimeException($exception));
            }
        }
    }

    public function getZoomUser($provider, $email)
    {
        try {
            $access_token = $this->retrieveAccessToken($provider);
            $response = $this->client->request(
                'GET',
                "/v2/users/sadiqzufar@gmail.com",
                ["headers" =>
                    ["Authorization" => "Bearer $access_token"]
                ]
            );
            $responseBody = json_decode($response->getBody());
            return $responseBody;
        } catch (\Exception $exception) {
            if ($exception->getCode() == 404) {
                return false;
            }
            if ($exception->getCode() == 401) {
                $this->refreshToken($provider);
                $this->getZoomUser($provider, $email);
            } else {
                Bugsnag::notifyException(new RuntimeException($exception));
            }
        }
    }


    public function saveToken($access_token, $refresh_token, $provider, $account_id)
    {
        if ($provider->config && isset($provider->config->data) && !empty($provider->config->data)) {
            if (gettype($provider->config->data)=="array") {
                $data = $provider->config->data;
            } elseif (gettype($provider->config->data)=="string") {
                $data = json_decode($provider->config->data, true);
            }
            $data['access_token'] = $access_token;
            $data['refresh_token'] = $refresh_token;
            $provider->config->data = $data;
            $attachData = $provider->config->toArray(); //account_provider object array
            $this->provider->configData("zoom", $provider->config['account_id'], $attachData);
        } else {
            $data = [];
            $data['access_token'] = $access_token;
            $data['refresh_token'] = $refresh_token;
            $attachData = [
                'data' => $data
            ];
            $this->provider->configData("zoom", $account_id, $attachData);
        }
    }

    public function retrieveAccessToken($provider)
    {
        $data = $provider->config->data;
        $type = gettype($data);

        if ($type=="string") {
            $tokens = json_decode($data);
            return $tokens->access_token;
        }

        if ($type=="array") {
            return $data['access_token'];
        }
    }

    public function retrieveRefreshToken($provider)
    {
        $data = $provider->config->data;
        $type = gettype($data);

        if ($type=="string") {
            $tokens = json_decode($data);
            return $tokens->refresh_token;
        }

        if ($type=="array") {
            return $data['refresh_token'];
        }
    }
}
