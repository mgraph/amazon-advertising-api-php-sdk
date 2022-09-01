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
        "version3"     => false,
        "sandbox"      => false];

    private $apiVersion         = null;
    private $applicationVersion = null;
    private $userAgent          = null;
    private $endpoint           = null;
    private $tokenUrl           = null;
    private $requestId          = null;
    private $endpoints          = null;
    private $versionStrings     = null;
    private $retryCounter       = 0;

    public $profileId = null;

    public function __construct($config)
    {
        $regions         = new Regions();
        $this->endpoints = $regions->endpoints;


        $versions             = new Versions();
        $this->versionStrings = $versions->versionStrings;

        $this->apiVersion         = $this->versionStrings["apiVersion"];
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

    public function listProfiles()
    {
        return $this->_operation("profiles");
    }

    public function registerProfile($data)
    {
        return $this->_operation("profiles/register", $data, "PUT");
    }

    public function registerProfileBrand($data)
    {
        return $this->_operation("profiles/registerBrand", $data, "PUT");
    }

    public function registerProfileStatus($profileId)
    {
        return $this->_operation("profiles/register/{$profileId}/status");
    }

    public function getProfile($profileId)
    {
        return $this->_operation("profiles/{$profileId}");
    }

    public function updateProfiles($data)
    {
        return $this->_operation("profiles", $data, "PUT");
    }

    public function getPortfolio($portfolioId)
    {
        return $this->_operation("portfolios/{$portfolioId}");
    }

    public function getPortfolioEx($portfolioId)
    {
        return $this->_operation("portfolios/extended/{$portfolioId}");
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

    public function listPortfoliosEx($data = null)
    {
        return $this->_operation("portfolios/extended", $data);
    }

    public function getCampaign($campaignId, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaigns/{$campaignId}", [], 'GET', $campainType);
    }

    public function getCampaignEx($campaignId, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaigns/extended/{$campaignId}", [], 'GET', $campainType);
    }

    public function createCampaigns($data)
    {
        return $this->_operation("campaigns", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateCampaigns($data, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaigns", $data, "PUT", $campainType);
    }

    public function archiveCampaign($campaignId, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        $this->archiveNegativeCampaignKeywords($campaignId, $campainType);
        $this->archiveCampaignAdGroups($campaignId);

        return $this->_operation("campaigns/{$campaignId}", null, "DELETE", $campainType);
    }

    public function archiveNegativeKeywordsByAdGroup($adGroupId)
    {
        return $this->archiveBulk(
            'NegativeKeywords',
            [Constants::FILTER_ADGROUP_ID => $adGroupId],
            'negative keyword',
            __METHOD__,
            Constants::KEYWORD_ID
        );
    }

    public function archiveNegativeCampaignKeywords($campaignId, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        if ($campainType === CampaignTypes::SPONSORED_BRANDS) {
            return;
        }

        return $this->archiveBulk(
            'CampaignNegativeKeywords',
            [Constants::FILTER_CAMPAIGN_ID => $campaignId],
            'negative keyword',
            __METHOD__,
            Constants::KEYWORD_ID,
            Constants::STATE_DELETED
        );
    }

    public function archiveAdsByAdGroup($adGroupId)
    {
        return $this->archiveBulk(
            'ProductAds',
            [Constants::FILTER_ADGROUP_ID => $adGroupId],
            'adGroup',
            __METHOD__,
            Constants::AD_ID
        );
    }

    public function archiveBiddableKeywordsByAdGroup($adGroupId)
    {
        return $this->archiveBulk(
            'BiddableKeywords',
            [Constants::FILTER_ADGROUP_ID => $adGroupId],
            'biddable keyword',
            __METHOD__,
            Constants::KEYWORD_ID
        );
    }

    public function archiveTargetingClausesByAdGroup($adGroupId)
    {
        return $this->archiveBulk(
            'TargetingClauses',
            [Constants::FILTER_ADGROUP_ID => $adGroupId],
            'targeting clause',
            __METHOD__,
            Constants::TARGET_ID,
            Constants::STATE_ARCHIVED
        );
    }


    private function archiveCampaignAdGroups($campaignId)
    {
        $startIndex = 0;

        while (true) {
            $adGroupListResponse = $this->listAdGroups([
                Constants::FILTER_CAMPAIGN_ID => $campaignId,
                Constants::FILTER_STARTINDEX  => $startIndex,
                Constants::FILTER_STATE       => implode(',', [Constants::STATE_ENABLED, Constants::STATE_PAUSED])
            ]);

            if ($adGroupListResponse['code'] !== 200) {
                throw new \Exception('Unable to load adGroups list for archiveCampaignAdGroups');
            }

            $adGroups = json_decode($adGroupListResponse['response'], true);

            if (\count($adGroups) === 0) {
                break;
            }

            foreach ($adGroups as $adGroup) {
                $this->archiveAdGroup($adGroup[Constants::AD_ADGROUP_ID]);
            }

            $startIndex++;
        }
    }


    private function archiveBulk(
        string $type,
        array $getListData,
        string $name,
        string $method,
        string $itemIdKey,
        string $state = Constants::STATE_ARCHIVED
    )
    {
        $startIndex     = 0;
        $processedCount = 0;

        while (true) {
            $keywordListResponse = $this->{"list$type"}($getListData + [
                    Constants::FILTER_STARTINDEX => $startIndex,
                    Constants::FILTER_STATE      => implode(',', [Constants::STATE_ENABLED, Constants::STATE_PAUSED])
                ]);

            if (200 !== $keywordListResponse['code']) {
                throw new \Exception(sprintf('Unable to load %s list for %s', $name, $method));
            }

            $keywords = json_decode($keywordListResponse['response'], true);

            if ($keywords === []) {
                break;
            }

            $keywordsToUpdate = [];

            foreach ($keywords as $keyword) {
                if (!isset($keyword[$itemIdKey]) ||
                    ($type === 'TargetingClauses' && $keyword[Constants::TARGET_EXPRESSION_TYPE] === Constants::TARGET_EXPRESSION_TYPE_AUTO)) {
                    continue;
                }

                $keywordsToUpdate[] = [$itemIdKey => $keyword[$itemIdKey], Constants::AD_STATE => $state];
                $processedCount++;
            }

            $keywordsToUpdateChunked = array_chunk($keywordsToUpdate, Constants::UPDATES_MAX_COUNT);

            foreach ($keywordsToUpdateChunked as $keywordsChunk) {
                $response = $this->{"update$type"}($keywordsChunk);

                if (207 !== $response['code']) {
                    $message = 'Unable to archive %ss clause in  %s. Request id: %s';
                    throw new \Exception(sprintf($message, $name, $method, $response['requestId']));
                }

                $keywordsResponse = json_decode($response['response'], true);

                foreach ($keywordsResponse as $response) {
                    if (!isset($response[$itemIdKey])) {
                        $message = 'Unable to archive %s clause in %s. Code: %s. Error: %s';
                        throw new \Exception(sprintf($message, $name, $method, $response['code'], $response['description']));
                    }
                }
            }

            $startIndex++;
        }

        return $processedCount;
    }


    private function archiveKeywordsByAdGroup($adGroupId)
    {
        $this->archiveBiddableKeywordsByAdGroup($adGroupId);
        $this->archiveNegativeKeywordsByAdGroup($adGroupId);
    }

    public function listCampaigns($data = null, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaigns", $data, 'GET', $campainType);
    }

    public function listCampaignsEx($data = null, $campainType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("campaigns/extended", $data, 'GET', $campainType);
    }

    public function getAdGroup($adGroupId)
    {
        return $this->_operation("adGroups/{$adGroupId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getAdGroupEx($adGroupId)
    {
        return $this->_operation("adGroups/extended/{$adGroupId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function createAdGroups($data)
    {
        return $this->_operation("adGroups", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateAdGroups($data)
    {
        return $this->_operation("adGroups", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function archiveAdGroupDependencies($adGroupId)
    {
        $this->archiveKeywordsByAdGroup($adGroupId);
        $this->archiveAdsByAdGroup($adGroupId);
        $this->archiveTargetingClausesByAdGroup($adGroupId);
    }

    public function archiveAdGroup($adGroupId)
    {
        return $this->_operation("adGroups/{$adGroupId}", null, "DELETE", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listAdGroups($data = null)
    {
        return $this->_operation("adGroups", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listAdGroupsEx($data = null)
    {
        return $this->_operation("adGroups/extended", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getBiddableKeyword($keywordId, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("keywords/{$keywordId}", [], 'GET', $campaignType);
    }

    public function getBiddableKeywordEx($keywordId)
    {
        return $this->_operation("keywords/extended/{$keywordId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function createBiddableKeywords($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("keywords", $data, "POST", $campaignType);
    }

    public function updateBiddableKeywords($data, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("keywords", $data, "PUT", $campaignType);
    }

    public function archiveBiddableKeyword($keywordId, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("keywords/{$keywordId}", null, "DELETE", $campaignType);
    }

    public function listBiddableKeywords($data = null)
    {
        return $this->_operation("keywords", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listBiddableKeywordsEx($data = null)
    {
        return $this->_operation("keywords/extended", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getNegativeKeyword($keywordId)
    {
        return $this->_operation("negativeKeywords/{$keywordId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getNegativeKeywordEx($keywordId)
    {
        return $this->_operation("negativeKeywords/extended/{$keywordId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function createNegativeKeywords($data)
    {
        return $this->_operation("negativeKeywords", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateNegativeKeywords($data)
    {
        return $this->_operation("negativeKeywords", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function archiveNegativeKeyword($keywordId)
    {
        return $this->_operation("negativeKeywords/{$keywordId}", null, "DELETE", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listNegativeKeywords($data = null)
    {
        return $this->_operation("negativeKeywords", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listNegativeKeywordsEx($data = null)
    {
        return $this->_operation("negativeKeywords/extended", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getCampaignNegativeKeyword($keywordId)
    {
        return $this->_operation("campaignNegativeKeywords/{$keywordId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getCampaignNegativeKeywordEx($keywordId)
    {
        return $this->_operation("campaignNegativeKeywords/extended/{$keywordId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

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
        return $this->_operation("campaignNegativeKeywords", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listCampaignNegativeKeywordsEx($data = null)
    {
        return $this->_operation("campaignNegativeKeywords/extended", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getProductAd($productAdId)
    {
        return $this->_operation("productAds/{$productAdId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getProductAdEx($productAdId)
    {
        return $this->_operation("productAds/extended/{$productAdId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function createProductAds($data)
    {
        return $this->_operation("productAds", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateProductAds($data)
    {
        return $this->_operation("productAds", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function archiveProductAd($productAdId)
    {
        return $this->_operation("productAds/{$productAdId}", null, "DELETE", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listProductAds($data = null)
    {
        return $this->_operation("productAds", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listProductAdsEx($data = null)
    {
        return $this->_operation("productAds/extended", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getTargetingClause($targetId)
    {
        return $this->_operation("targets/{$targetId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getTargetingClauseEx($targetId)
    {
        return $this->_operation("targets/extended/{$targetId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function createTargetingClauses($data)
    {
        return $this->_operation("targets", $data, "POST", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateTargetingClauses($data)
    {
        return $this->_operation("targets", $data, "PUT", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function archiveTargetingClause($targetId)
    {
        return $this->_operation("targets/{$targetId}", null, "DELETE", CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function createTargetRecommendations($data = null)
    {
        return $this->_operation("targets/productRecommendations", $data, 'POST', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getTargetingCategories($data)
    {
        return $this->_operation("targets/categories", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getBrandRecommendations($data)
    {
        return $this->_operation("targets/brands", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listTargetingClauses($data = null)
    {
        return $this->_operation("targets", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listTargetingClausesEx($data = null)
    {
        return $this->_operation("targets/extended", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getNegativeTargetingClause($targetId)
    {
        return $this->_operation("negativeTargets/{$targetId}", null, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getNegativeTargetingClauseEx($targetId)
    {
        return $this->_operation("negativeTargets/extended/{$targetId}", null, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listNegativeTargetingClauses($data = null)
    {
        return $this->_operation("negativeTargets", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listCampaignNegativeTargetingClauses($data = null)
    {
        // todo: check how to handle this because this not official
        return $this->_operation("campaignNegativeTargets", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }
    
    public function getCampaignNegativeTargetingClause($targetId)
    {
        return $this->_operation("campaignNegativeTargets/{$targetId}", [], 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function createCampaignNegativeTargetingClauses($data)
    {
        return $this->_operation("campaignNegativeTargets", $data, 'POST', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateCampaignNegativeTargetingClause($data)
    {
        return $this->_operation("campaignNegativeTargets", $data, 'PUT', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function archiveCampaignNegativeTargetingClause($targetId)
    {
        return $this->_operation("campaignNegativeTargets/{$targetId}", null, 'DELETE', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function listNegativeTargetingClausesEx($data = null)
    {
        return $this->_operation("negativeTargets/extended", $data, 'GET', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function createNegativeTargetingClauses($data)
    {
        return $this->_operation("negativeTargets", $data, 'POST', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function updateNegativeTargetingClauses($data)
    {
        return $this->_operation("negativeTargets", $data, 'PUT', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function archiveNegativeTargetingClause($targetId)
    {
        return $this->_operation("negativeTargets/{$targetId}", null, 'DELETE', CampaignTypes::SPONSORED_PRODUCTS);
    }

    public function getAdGroupBidRecommendations($adGroupId)
    {
        return $this->_operation("adGroups/{$adGroupId}/bidRecommendations");
    }

    public function getKeywordBidRecommendations($keywordId)
    {
        return $this->_operation("keywords/{$keywordId}/bidRecommendations");
    }

    public function bulkGetKeywordBidRecommendations($adGroupId, $data)
    {
        $data = [
            "adGroupId" => $adGroupId,
            "keywords"  => $data];

        return $this->_operation("keywords/bidRecommendations", $data, "POST");
    }

    public function getAdGroupKeywordSuggestions($data)
    {
        $adGroupId = $data["adGroupId"];
        unset($data["adGroupId"]);

        return $this->_operation("adGroups/{$adGroupId}/suggested/keywords", $data);
    }

    public function getAdGroupKeywordSuggestionsEx($data)
    {
        $adGroupId = $data["adGroupId"];
        unset($data["adGroupId"]);

        return $this->_operation("adGroups/{$adGroupId}/suggested/keywords/extended", $data);
    }

    public function getAsinKeywordSuggestions($data)
    {
        $asin = $data["asin"];
        unset($data["asin"]);

        return $this->_operation("asins/{$asin}/suggested/keywords", $data);
    }

    public function bulkGetAsinKeywordSuggestions($data)
    {
        return $this->_operation("asins/suggested/keywords", $data, "POST");
    }

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

    public function requestReportV2($recordType, $data = null, $campaignType = CampaignTypes::SPONSORED_PRODUCTS)
    {
        return $this->_operation("{$recordType}/report", $data, "POST", $campaignType);
    }

    public function getReportV2($reportId)
    {
        $req = $this->_operation("reports/{$reportId}");
        if ($req["success"]) {
            $json = json_decode($req["response"], true);
            if ($json["status"] == "SUCCESS") {
                return $this->_download($json["location"]);
            }
        }

        return $req;
    }

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
        $content_type = 'application/json';

        if ($this->config["version3"]) {
            $content_type = 'application/vnd.createasyncreportrequest.v3+json';
        }

        $headers = [
            "Authorization: bearer {$this->config["accessToken"]}",
            "Amazon-Advertising-API-ClientId: {$this->config['clientId']}",
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
                    "requestId" => $requestId];
        } else {
            return ["success"   => true,
                    "code"      => $response_info["http_code"],
                    "response"  => $response,
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
            if (is_null($v) && $k !== "accessToken" && $k !== "refreshToken") {
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
                case "sandbox":
                    if (!is_bool($v)) {
                        $this->_logAndThrow("Invalid parameter value for sandbox.");
                    }
                    break;
                case "version3":
                    if (!is_bool($v)) {
                        $this->_logAndThrow("Invalid parameter value for version3.");
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
                           
            if ($this->config["sandbox"]) {
                $this->endpoint = "https://{$this->endpoints[$region_code]["sandbox"]}/{$this->apiVersion}";
            } else {
                $this->endpoint = "https://{$this->endpoints[$region_code]["prod"]}/{$this->apiVersion}";
            }

            if ($this->config["version3"]) {
                $this->endpoint = "https://{$this->endpoints[$region_code]["prod"]}";
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
}
