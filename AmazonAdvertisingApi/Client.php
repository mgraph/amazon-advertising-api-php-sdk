<?php

namespace AmazonAdvertisingApi;

require_once "Versions.php";
require_once "CampaignTypes.php";
require_once "Regions.php";
require_once "CurlRequest.php";
require_once "Constants.php";

class Client
{
    public const RETRY_SLEEP_TIME    = 5;
    public const MAX_RETRIES         = 30;
    public const SERVER_IS_BUSY_CODE = 'SERVER_IS_BUSY';

    public static $http_codes_temp_issue = [429, 500];

    private $config = [
        "clientId"     => null,
        "clientSecret" => null,
        "region"       => null,
        "accessToken"  => null,
        "refreshToken" => null,
        'apiVersion'   => '',
    ];

    private $apiVersion         = null;
    private $applicationVersion = null;
    private $userAgent          = null;
    private $endpoint           = null;
    private $tokenUrl           = null;
    private $requestId          = null;
    private $endpoints          = null;
    private $versionStrings     = null;
    private $retryCounter       = 0;
    private $fullUrl = '';
    private $contentType = '';

    public $profileId = null;

    public function __construct($config)
    {
        $regions         = new Regions();
        $this->endpoints = $regions->endpoints;

        $versions             = new Versions();
        $this->versionStrings = $versions->versionStrings;

        $this->apiVersion = $config['apiVersion'] ?? null;

        $this->apiVersion = is_null($this->apiVersion) ? $this->versionStrings["apiVersion"] : $this->apiVersion;

        $this->applicationVersion = $this->versionStrings["applicationVersion"];
        $this->userAgent          = "AdvertisingAPI PHP Client Library v{$this->applicationVersion}";

        $this->_validateConfig($config);


        $this->_validateConfigParameters();
        $this->_setEndpoints();

        if (is_null($this->config["accessToken"]) && !is_null($this->config["refreshToken"])) {
            /* convenience */
            $this->doRefreshToken();
        }
    }

    public function doRefreshToken()
    {
        $headers = [
            "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
            "User-Agent: {$this->userAgent}"
        ];

        $refresh_token = rawurldecode($this->config["refreshToken"]);

        $params = [
            "grant_type"    => "refresh_token",
            "refresh_token" => $refresh_token,
            "client_id"     => $this->config["clientId"],
            "client_secret" => $this->config["clientSecret"]];

        $data = "";
        foreach ($params as $k => $v) {
            $data .= "{$k}=" . rawurlencode($v) . "&";
        }

        $url = "https://{$this->tokenUrl}";

        $request = new CurlRequest($url);
        $request->setOption(CURLOPT_URL, $url);
        $request->setOption(CURLOPT_HTTPHEADER, $headers);
        $request->setOption(CURLOPT_USERAGENT, $this->userAgent);
        $request->setOption(CURLOPT_POST, true);
        $request->setOption(CURLOPT_POSTFIELDS, rtrim($data, "&"));

        $response = $this->_executeRequest($request);

        $response_array = json_decode($response["response"], true);
        if(!is_null($response_array)){
            if (array_key_exists("access_token", $response_array)) {
                $this->config["accessToken"] = $response_array["access_token"];
            } else {
                $this->_logAndThrow("Unable to refresh token. 'access_token' not found in response. " . print_r($response, true));
            }
        }else{
            $this->_logAndThrow("doRefreshToken: response_array is null " . print_r($response, true));
        }

        return $response;
    }

    public function listTestAccounts()
    {
        return $this->_operation("testAccounts");
    }

    public function listManagerAccounts()
    {
        return $this->_operation("managerAccounts");
    }

    // profiles
    public function listProfiles()
    {
        return $this->_operation("profiles");
    }

    public function getProfile($profileId)
    {
        return $this->_operation("profiles/{$profileId}");
    }

    public function updateProfiles($data)
    {
        return $this->_operation("profiles", $data, "PUT");
    }

    // portfolios
    public function getPortfolio($portfolioId)
    {
        return $this->_operation("portfolios/{$portfolioId}");
    }

    public function createPortfolios($data)
    {
        return $this->_operation("portfolios", $data, "POST");
    }

    public function updatePortfolios($data)
    {
        return $this->_operation("portfolios", $data, "PUT");
    }

    public function listPortfolios($data = null)
    {
        return $this->_operation("portfolios", $data);
    }

    // campaigns
    public function createCampaigns($data)
    {
        return $this->_operation("campaigns", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateCampaigns($data, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaigns", $data, "PUT", $campainType);
    }

    public function listCampaigns($data = null, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaigns/list", $data, "POST", $campainType);
    }

    // adGroups
    public function createAdGroups($data)
    {
        return $this->_operation("adGroups", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateAdGroups($data)
    {
        return $this->_operation("adGroups", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listAdGroups($data = null)
    {
        return $this->_operation("adGroups/list", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getAdGroupBidRecommendations($adGroupId)
    {
        return $this->_operation("adGroups/{$adGroupId}/bidRecommendations");
    }

    // keywords
    public function createBiddableKeywords($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("keywords", $data, "POST", $campaignType);
    }

    public function updateBiddableKeywords($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("keywords", $data, "PUT", $campaignType);
    }

    public function getKeywordBidRecommendations($keywordId)
    {
        return $this->_operation("keywords/{$keywordId}/bidRecommendations");
    }

    public function listBiddableKeywords($data = null)
    {
        return $this->_operation("keywords/list", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    // negativeKeywords
    public function createNegativeKeywords($data)
    {
        return $this->_operation("negativeKeywords", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateNegativeKeywords($data)
    {
        return $this->_operation("negativeKeywords", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listNegativeKeywords($data = null)
    {
        return $this->_operation("negativeKeywords/list", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    // campaignNegativeKeywords
    public function createCampaignNegativeKeywords($data)
    {
        return $this->_operation("campaignNegativeKeywords", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateCampaignNegativeKeywords($data)
    {
        return $this->_operation("campaignNegativeKeywords", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function removeCampaignNegativeKeyword($keywordId)
    {
        return $this->_operation("campaignNegativeKeywords/{$keywordId}", null, "DELETE", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listCampaignNegativeKeywords($data = null)
    {
        return $this->_operation("campaignNegativeKeywords/list", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    // productAds
    public function createProductAds($data)
    {
        return $this->_operation("productAds", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateProductAds($data)
    {
        return $this->_operation("productAds", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listProductAds($data = null)
    {
        return $this->_operation("productAds/list", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    // targets
    public function createTargetingClauses($data)
    {
        return $this->_operation("targets", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateTargetingClauses($data)
    {
        return $this->_operation("targets", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listTargetingClauses($data = null)
    {
        return $this->_operation("targets/list", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    // negativeTargets
    public function createNegativeTargetingClauses($data)
    {
        return $this->_operation("negativeTargets", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateNegativeTargetingClauses($data)
    {
        return $this->_operation("negativeTargets", $data, 'PUT', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listNegativeTargetingClauses($data = null)
    {
        return $this->_operation("negativeTargets/list", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    // campaignNegativeTargets

    public function createCampaignNegativeTargetingClauses($data)
    {
        return $this->_operation("campaignNegativeTargets", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateCampaignNegativeTargetingClause($data)
    {
        return $this->_operation("campaignNegativeTargets", $data, 'PUT', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listCampaignNegativeTargetingClauses($data = null)
    {
        return $this->_operation("campaignNegativeTargets/list", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    // snapshot
    public function requestSnapshot($recordType, $data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("{$recordType}/snapshot", $data, "POST", $campaignType);
    }

    public function getSnapshot($snapshotId)
    {
        $req = $this->_operation("snapshots/{$snapshotId}");
        if ($req["success"]) {
            $json = json_decode($req["response"], true);
            if ($json["status"] == "SUCCESS") {
                return $this->_download($json["location"]);
            }
        }

        return $req;
    }

    // reports
    public function requestReport($data = null) // v3
    {
        return $this->_operation("reporting/reports", $data, "POST");
    }

    public function getReport($reportId) // v3
    {
        $req = $this->_operation("reporting/reports/{$reportId}");
        if ($req["success"]) {
            $json = json_decode($req["response"], true);

			if ($json["status"] == "COMPLETED") {
                return $this->_download($json["url"], true);
            }
        }

        return $req;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    private function _download($location, $gunzip = false)
    {
        $headers = [];

        if (!$gunzip) {
            /* only send authorization header when not downloading actual file */
            array_push($headers, "Authorization: bearer {$this->config["accessToken"]}");
        }

        if (!is_null($this->profileId)) {
            array_push($headers, "Amazon-Advertising-API-Scope: {$this->profileId}");
        }

        $request = new CurlRequest();
        $request->setOption(CURLOPT_URL, $location);
        $request->setOption(CURLOPT_HTTPHEADER, $headers);
        $request->setOption(CURLOPT_USERAGENT, $this->userAgent);

        if ($gunzip) {
            $response             = $this->_executeRequest($request);
            $response["response"] = gzdecode($response["response"]);

            return $response;
        }

        return $this->_executeRequest($request);
    }

    private function _operation($interface, $params = [], $method = "GET", $campaintType = '')
    {
        $content_type = $this->getContentType($interface, $campaintType);

        $headers = [
            "Authorization: bearer {$this->config["accessToken"]}",
            "Amazon-Advertising-API-ClientId: {$this->config['clientId']}",
            "Accept: " . $content_type,
            "Content-Type: " . $content_type,
            "User-Agent: {$this->userAgent}"
        ];

        if (!is_null($this->profileId)) {
            array_push($headers, "Amazon-Advertising-API-Scope: {$this->profileId}");
        }

        $request         = new CurlRequest();
        $campaintType    = $campaintType ? "$campaintType/" : '';
        $url             = "{$this->endpoint}/{$campaintType}{$interface}";
        $this->requestId = null;
        $data            = "";

        switch (strtolower($method)) {
            case "get":
                if (!empty($params)) {
                    $url .= "?";
                    foreach ($params as $k => $v) {
                        $url .= "{$k}=" . rawurlencode($v) . "&";
                    }
                    $url = rtrim($url, "&");
                }
                break;
            case "put":
            case "post":
            case "delete":
                if (!empty($params)) {
                    $data = json_encode($params);
                    $request->setOption(CURLOPT_POST, true);
                    $request->setOption(CURLOPT_POSTFIELDS, $data);
                }
                break;
            default:
                $this->_logAndThrow("Unknown verb {$method}.");
        }

        $this->fullUrl = $url;
        $this->contentType = $content_type;

        $request->setOption(CURLOPT_URL, $url);
        $request->setOption(CURLOPT_HTTPHEADER, $headers);
        $request->setOption(CURLOPT_USERAGENT, $this->userAgent);
        $request->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($method));

        return $this->_executeRequest($request);
    }

    protected function _executeRequest($request)
    {
        $response        = $request->execute();
        $this->requestId = $request->requestId;
        $response_info   = $request->getInfo();

        if (in_array($response_info["http_code"], self::$http_codes_temp_issue) && $this->retryCounter < self::MAX_RETRIES) {
            sleep(self::RETRY_SLEEP_TIME);
            $this->retryCounter++;
            $this->_executeRequest($request);
        }

        $request->close();

        if ($response_info["http_code"] == 307) {
            /* application/octet-stream */
            return $this->_download($response_info["redirect_url"], true);
        }

        if (!preg_match("/^(2|3)\d{2}$/", $response_info["http_code"])) {
            $requestId = 0;
            $json      = json_decode($response, true);
            if (!is_null($json)) {
                if (array_key_exists("requestId", $json)) {
                    $requestId = json_decode($response, true)["requestId"];
                }
            }

            return ["success"   => false,
                    "code"      => $response_info["http_code"],
                    "response"  => $response,
                    "fullUrl"   => $this->fullUrl,
                    "contentType"   => $this->contentType,
                    "requestId" => $requestId];
        } else {
            return ["success"   => true,
                    "code"      => $response_info["http_code"],
                    "response"  => $response,
                    "fullUrl"   => $this->fullUrl,
                    "contentType"   => $this->contentType,
                    "requestId" => $this->requestId];
        }
    }

    private function _validateConfig($config)
    {
        if (is_null($config)) {
            $this->_logAndThrow("'config' cannot be null.");
        }

        foreach ($config as $k => $v) {
            if (array_key_exists($k, $this->config)) {
                $this->config[$k] = $v;
            } else {
                $this->_logAndThrow("Unknown parameter '{$k}' in config.");
            }
        }

        return true;
    }

    private function _validateConfigParameters()
    {
        foreach ($this->config as $k => $v) {
            if (is_null($v) && $k !== "accessToken" && $k !== "refreshToken" && $k !== "apiVersion") {
                $this->_logAndThrow("Missing required parameter '{$k}'.");
            }
            switch ($k) {
                case "clientId":
                    if (!preg_match("/^amzn1\.application-oa2-client\.[a-z0-9]{32}$/i", $v)) {
                        $this->_logAndThrow("Invalid parameter value for clientId.");
                    }
                    break;
                case "clientSecret":
                    if (!preg_match("/^[a-z0-9]{64}$/i", $v)) {
                        $this->_logAndThrow("Invalid parameter value for clientSecret.");
                    }
                    break;
                case "accessToken":
                    if (!is_null($v)) {
                        if (!preg_match("/^Atza(\||%7C|%7c).*$/", $v)) {
                            $this->_logAndThrow("Invalid parameter value for accessToken.");
                        }
                    }
                    break;
                case "refreshToken":
                    if (!is_null($v)) {
                        if (!preg_match("/^Atzr(\||%7C|%7c).*$/", $v)) {
                            $this->_logAndThrow("Invalid parameter value for refreshToken.");
                        }
                    }
                    break;
            }
        }

        return true;
    }

    private function _setEndpoints()
    {
        /* check if region exists and set api/token endpoints */
        if (array_key_exists(strtolower($this->config["region"]), $this->endpoints)) {
            $region_code = strtolower($this->config["region"]);

            if (empty($this->apiVersion)) {
                $this->endpoint = "https://{$this->endpoints[$region_code]["prod"]}";
            } else {
                $this->endpoint = "https://{$this->endpoints[$region_code]["prod"]}/{$this->apiVersion}";
            }

            $this->tokenUrl = $this->endpoints[$region_code]["tokenUrl"];
        } else {
            $this->_logAndThrow("Invalid region.");
        }

        return true;
    }

    private function _logAndThrow($message)
    {
        error_log($message, 0);
        throw new \Exception($message);
    }

    private function getContentType($interface, $campaintType){
        $content_type = 'application/json';

        if (stripos($interface, 'snapshot')) {
            return $content_type;
        }

        if (stripos($interface, 'reporting/reports') !== false) { // v3
            $content_type = 'application/vnd.createasyncreportrequest.v3+json';
        }

        if ($interface == 'brands') { // v3
            $content_type = 'application/vnd.brand.v3+json';
        }

        if (stripos($interface, 'campaigns') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spcampaign.v3+json';
            }
        }

        if (stripos($interface, 'adgroups') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spAdGroup.v3+json';
            }
        }
        
        if (stripos($interface, 'keywords') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spKeyword.v3+json';
            }
        }

        if (stripos($interface, 'CampaignNegativeKeyword') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spCampaignNegativeKeyword.v3+json';
            }
        }

        if (stripos($interface, 'negativeKeywords') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spNegativeKeyword.v3+json';
            }
        }

        if (stripos($interface, 'negativeTargets') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spNegativeTargetingClause.v3+json';
            }
        }

        if (stripos($interface, 'campaignNegativeTargets') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spCampaignNegativeTargetingClause.v3+json';
            }
        }

        if (stripos($interface, 'ProductAds') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spProductAd.v3+json';
            }
        }

        if (stripos($interface, 'targets') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) { // v4
                // $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spTargetingClause.v3+json';
            }
        }

        return $content_type;
    }
}
