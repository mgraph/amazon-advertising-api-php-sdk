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
    private $acceptHeader = '';

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
    
    public function getBrands()
    {
        return $this->_operation("brands");
    }

    public function productMetadata($data)
    {
        return $this->_operation("product/metadata", $data, "POST");
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
    public function createCampaigns($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaigns", $data, "POST", $campaignType);
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
    public function createAdGroups($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("adGroups", $data, "POST", $campaignType);
    }

    public function updateAdGroups($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("adGroups", $data, "PUT", $campaignType);
    }

    public function listAdGroups($data = null, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("adGroups/list", $data, "POST", $campainType);
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

    public function listBiddableKeywords($data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        if($campaignType == CampaignTypes::SPONSORED_BRANDS){
           return $this->_operation("keywords", $data, "GET", $campaignType);
        }

        return $this->_operation("keywords/list", $data, "POST", $campaignType);
    }

    // negativeKeywords
    public function createNegativeKeywords($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("negativeKeywords", $data, "POST", $campaignType);
    }

    public function updateNegativeKeywords($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("negativeKeywords", $data, "PUT", $campaignType);
    }

    public function listNegativeKeywords($data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        if($campaignType == CampaignTypes::SPONSORED_BRANDS){
            return $this->_operation("negativeKeywords", $data, "GET", $campaignType);
        }

        return $this->_operation("negativeKeywords/list", $data, "POST", $campaignType);
    }

    // campaignNegativeKeywords
    public function createCampaignNegativeKeywords($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaignNegativeKeywords", $data, "POST", $campaignType);
    }

    public function updateCampaignNegativeKeywords($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaignNegativeKeywords", $data, "PUT", $campaignType);
    }

    public function removeCampaignNegativeKeyword($keywordId, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaignNegativeKeywords/{$keywordId}", null, "DELETE", $campaignType);
    }

    public function listCampaignNegativeKeywords($data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaignNegativeKeywords/list", $data, "POST", $campaignType);
    }

    // productAds
    public function createProductAds($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("productAds", $data, "POST", $campaignType);
    }

    public function updateProductAds($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("productAds", $data, "PUT", $campaignType);
    }

    public function listProductAds($data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("productAds/list", $data, "POST", $campaignType);
    }

    // Brand Ads
    public function listBrandAds($data = null, $campaignType = CampaignTypes::SPONSORED_BRANDS)
    {
        return $this->_operation("ads/list", $data, "POST", $campaignType);
    }

    // targets
    public function createTargetingClauses($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("targets", $data, "POST", $campaignType);
    }

    public function updateTargetingClauses($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("targets", $data, "PUT", $campaignType);
    }

    public function listTargetingClauses($data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("targets/list", $data, "POST", $campaignType);
    }

    // negativeTargets
    public function createNegativeTargetingClauses($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("negativeTargets", $data, "POST", $campaignType);
    }

    public function updateNegativeTargetingClauses($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("negativeTargets", $data, 'PUT', $campaignType);
    }

    public function listNegativeTargetingClauses($data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("negativeTargets/list", $data, "POST", $campaignType);
    }

    // campaignNegativeTargets

    public function createCampaignNegativeTargetingClauses($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaignNegativeTargets", $data, "POST", $campaignType);
    }

    public function updateCampaignNegativeTargetingClause($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaignNegativeTargets", $data, 'PUT', $campaignType);
    }

    public function listCampaignNegativeTargetingClauses($data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaignNegativeTargets/list", $data, "POST", $campaignType);
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
    public function requestReport($data = null, $recordType = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS) // v2 & v3
    {
        if($this->apiVersion == 'v2'){
            return $this->_operation("{$recordType}/report", $data, "POST", $campaignType);
        }

        return $this->_operation("reporting/reports", $data, "POST");
    }

    public function getReport($reportId) // v2 & v3
    {
        if($this->apiVersion == 'v2'){
            $req = $this->_operation("reports/{$reportId}");
        } else {
            $req = $this->_operation("reporting/reports/{$reportId}");
        }
        if ($req["success"]) {
            $json = json_decode($req["response"], true);

            if ($json["status"] == "SUCCESS" && isset($json["location"])) { // v2
                return $this->_download($json["location"]);
            }

			if ($json["status"] == "COMPLETED" && isset($json["url"])) { // v3
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
        $accept_header = $content_type;

        // SB Issue: code:415, details: Cannot consume content type
        if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
            if (in_array($interface, ['keywords', 'negativeKeywords', 'targets/list', 'negativeTargets/list'])) {
                $content_type = 'application/json';
            }
        }

        $headers = [
            "Authorization: bearer {$this->config["accessToken"]}",
            "Amazon-Advertising-API-ClientId: {$this->config['clientId']}",
            "Accept: " . $accept_header,
            "Content-Type: " . $content_type,
            "User-Agent: {$this->userAgent}"
        ];

        $this->contentType = $content_type;
        $this->acceptHeader = $accept_header;

        if (!is_null($this->profileId)) {
            array_push($headers, "Amazon-Advertising-API-Scope: {$this->profileId}");
        }

        $request         = new CurlRequest();
        $campaintType    = $campaintType ? "$campaintType/" : '';
        $url             = "{$this->endpoint}/{$campaintType}{$interface}";
        $this->requestId = null;
        $data            = "";

        $url = str_replace('/v4/sb/', '/sb/v4/', $url); // Sponsored Brand inconsistent URL

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
                    "response_content"   => $response_info["content_type"] ?? '',
                    "contentType"   => $this->contentType,
                    "acceptHeader"   => $this->acceptHeader,
                    "requestId" => $requestId];
        } else {
            return ["success"   => true,
                    "code"      => $response_info["http_code"],
                    "response"  => $response,
                    "fullUrl"   => $this->fullUrl,
                    "response_content"   => $response_info["content_type"] ?? '',
                    "contentType"   => $this->contentType,
                    "acceptHeader"   => $this->acceptHeader,
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

        if (stripos($interface, 'snapshot') !== false) {
            return $content_type;
        }

        if ($this->apiVersion == 'v2' && stripos($interface, 'report') !== false) {
            return $content_type;
        }
        
        if (stripos($interface, 'product/metadata') !== false) {
            return 'application/vnd.productmetadatarequest.v1+json';
        }

        if (stripos($interface, 'reporting/reports') !== false) { // v3
            $content_type = 'application/vnd.createasyncreportrequest.v3+json';
        }

        if ($interface == 'brands') { // v3
            $content_type = 'application/vnd.brand.v3+json';
        }

        if (stripos($interface, 'campaigns') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
                $content_type = 'application/vnd.sbcampaignresource.v4+json';
            }else{
                $content_type = 'application/vnd.spcampaign.v3+json';
            }
        }

        if (stripos($interface, 'adgroups') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
                $content_type = 'application/vnd.sbadgroupresource.v4+json';
            }else{
                $content_type = 'application/vnd.spAdGroup.v3+json';
            }
        }
        
        if (stripos($interface, 'keywords') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
                $content_type = 'application/vnd.sbkeyword.v3.2+json';
            }else{
                $content_type = 'application/vnd.spKeyword.v3+json';
            }
        }

        if (stripos($interface, 'CampaignNegativeKeyword') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
                
            }else{
                $content_type = 'application/vnd.spCampaignNegativeKeyword.v3+json';
            }
        }

        if (stripos($interface, 'negativeKeywords') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
                $content_type = 'application/vnd.sbnegativekeyword.v3.2+json';
            }else{
                $content_type = 'application/vnd.spNegativeKeyword.v3+json';
            }
        }

        if (stripos($interface, 'negativeTargets') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
                $content_type = 'application/vnd.sblistnegativetargetsresponse.v3.2+json';
            }else{
                $content_type = 'application/vnd.spNegativeTargetingClause.v3+json';
            }
        }

        if (stripos($interface, 'campaignNegativeTargets') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
                
            }else{
                $content_type = 'application/vnd.spCampaignNegativeTargetingClause.v3+json';
            }
        }

        if (stripos($interface, 'ProductAds') === 0) {
            $content_type = 'application/vnd.spProductAd.v3+json';
        }
        
        if (stripos($interface, 'ads') === 0) {
            $content_type = 'application/vnd.sbadresource.v4+json';
        }

        if (stripos($interface, 'targets') === 0) {
            if ($campaintType == CampaignTypes::SPONSORED_BRANDS) {
                $content_type = 'application/vnd.sblisttargetsresponse.v3.2+json';
            }else{
                $content_type = 'application/vnd.spTargetingClause.v3+json';
            }
        }

        return $content_type;
    }
}
