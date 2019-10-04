<?php

namespace AmazonAdvertisingApi;

require_once "../AmazonAdvertisingApi/Client.php";

class ClientIntegrationTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Client
     */
    private static $client = null;
    private static $config = array();

    public static function setUpBeforeClass()
    {
        self::$config = array(
            "clientId"     => $GLOBALS['CLIENT_ID'],
            "clientSecret" => $GLOBALS['CLIENT_SECRET'],
            "region"       => $GLOBALS['REGION'],
            "accessToken"  => null,
            "refreshToken" => $GLOBALS['REFRESH_TOKEN'],
            "sandbox"      => true
        );

        self::$client = new Client(self::$config);

        $response = self::$client->listProfiles();
        $profiles = json_decode($response['response'], true);
        $profile  = array_shift($profiles);

        self::$client->profileId = $profile['profileId'];
    }

    public function testListProfiles()
    {
        $response = self::$client->listProfiles();
        $this->assertSuccessResponse($response);
    }

    public function testGetProfile()
    {
        $response = self::$client->getProfile(self::$client->profileId);
        $this->assertSuccessResponse($response);
    }

    public function testRegisterProfile()
    {
        $data = array(
            'countryCode' => 'DE',
        );

        $response = self::$client->registerProfile($data);
        $this->assertSuccessResponse($response);
    }

    public function testRegisterProfileBrand()
    {
        $data = array(
            'countryCode' => 'DE',
            'brand'       => 'Test brand name',
        );

        $response = self::$client->registerProfileBrand($data);
        $this->assertSuccessResponse($response);
    }

    public function testUpdateProfiles()
    {
        $response = self::$client->updateProfiles(array(array(
            'profileId'   => self::$client->profileId,
            'dailyBudget' => 1,
        )));

        $this->assertSuccessResponse($response, 207);
    }


    public function testCreatePortfolios()
    {
        $data = array(
            array(
                'name'  => 'Test portfolio',
                'state' => 'enabled',
            )
        );

        $response = self::$client->createPortfolios($data);
        $this->assertSuccessResponse($response, 207);
    }

    /**
     * @return mixed
     */
    public function testListPortfolios()
    {
        $response = self::$client->listPortfolios([]);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @return mixed
     */
    public function testListPortfoliosEx()
    {
        $response = self::$client->listPortfoliosEx([]);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }


    /**
     * @depends testListPortfolios
     *
     * @param $portfolios
     */
    public function testUpdatePortfolios($portfolios)
    {
        $portfolio = array_shift($portfolios);

        $data = array(
            array(
                'portfolioId' => $portfolio['portfolioId'],
                'name'        => 'Test portfolio',
                'state'       => 'enabled',
            )
        );

        $response = self::$client->updatePortfolios($data);
        $this->assertSuccessResponse($response, 207);
    }


    /**
     * @depends testListPortfolios
     *
     * @param $portfolios
     */
    public function testGetPortfolio($portfolios)
    {
        $portfolio = array_shift($portfolios);

        $response = self::$client->getPortfolio($portfolio['portfolioId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListPortfoliosEx
     *
     * @param $portfolios
     */
    public function testGetPortfolioEx($portfolios)
    {
        $portfolio = array_shift($portfolios);

        $response = self::$client->getPortfolioEx($portfolio['portfolioId']);
        $this->assertSuccessResponse($response);
    }

    public function testCreateCampaigns()
    {
        $campaings = array(
            array(
                'name'          => 'Test Campaign',
                'targetingType' => 'auto',
                "campaignType"  => "sponsoredProducts",
                'state'         => 'enabled',
                'dailyBudget'   => 1,
                'startDate'     => '20190101',
            )
        );

        $response = self::$client->createCampaigns($campaings);
        $this->assertSuccessResponse($response, 207);
    }

    public function testListCampaigns()
    {
        $data = array(
            'startIndex'  => 1,
            'count'       => 1,
            'stateFilter' => 'enabled,paused',
        );

        $response = self::$client->listCampaigns($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    public function testListCampaignsEx()
    {
        $data = array(
            'startIndex'  => 1,
            'count'       => 1,
            'stateFilter' => 'enabled,paused',
        );

        $response = self::$client->listCampaignsEx($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @depends testListCampaigns
     *
     * @param $campaings
     */
    public function testGetCampaign($campaings)
    {
        $campaign = array_shift($campaings);

        $response = self::$client->getCampaign($campaign['campaignId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListCampaigns
     *
     * @param $campaings
     */
    public function testGetCampaignEx($campaings)
    {
        $campaign = array_shift($campaings);
        $response = self::$client->getCampaignEx($campaign['campaignId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListCampaigns
     *
     * @param $campaings
     */
    public function testUpdateCampaigns($campaings)
    {
        $campaing = array_shift($campaings);

        $campaings = array(
            array(
                'campaignId'    => $campaing['campaignId'],
                'name'          => 'Test Campaign',
                'targetingType' => 'manual',
                'state'         => 'enabled',
                'dailyBudget'   => 1,
                'startDate'     => '20180101',
            )
        );

        $response = self::$client->updateCampaigns($campaings);
        $this->assertSuccessResponse($response, 207);
    }

    /**
     *
     * @depends testListCampaigns
     *
     * @param $campaings
     */
    public function testArchiveCampaign($campaings)
    {
        $campaing = array_shift($campaings);
        $response = self::$client->archiveCampaign($campaing['campaignId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @return mixed
     */
    public function testListAdGroups()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listAdGroups($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @return mixed
     */
    public function testListAdGroupsEx()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listAdGroupsEx($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @depends testListAdGroups
     *
     * @param $groups
     */
    public function testGetAdGroup($groups)
    {
        $group = array_shift($groups);

        $response = self::$client->getAdGroup($group['adGroupId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListAdGroupsEx
     *
     * @param $groups
     */
    public function testGetAdGroupEx($groups)
    {
        $group = array_shift($groups);

        $response = self::$client->getAdGroupEx($group['adGroupId']);
        $this->assertSuccessResponse($response);
    }

    public function testCreateAdGroups()
    {
        $data = array(
            array(
                'campaignId' => 1,
                'name'       => 'Test group',
                'state'      => 'enabled',
                'defaultBid' => 1,
            )
        );

        $response = self::$client->createAdGroups($data);
        $this->assertSuccessResponse($response, 207);
    }

    /**
     * @depends testListAdGroups
     *
     * @param $groups
     */
    public function testUpdateAdGroups($groups)
    {
        $group = array_shift($groups);

        $data = array(
            array(
                'adGroupId' => $group['adGroupId'],
                'name'      => 'Test group',
                'state'     => 'enabled',
            )
        );

        $response = self::$client->updateAdGroups($data);
        $this->assertSuccessResponse($response, 207);
    }

    public function testArchiveAdGroup()
    {
        $response = self::$client->archiveAdGroup('test');

        $this->assertFalse($response['success']);
        $this->assertSame(404, $response['code']);
    }

    /**
     * @return mixed
     */
    public function testListBiddableKeywords()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listBiddableKeywords($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @return mixed
     */
    public function testListBiddableKeywordsEx()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listBiddableKeywordsEx($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @depends testListBiddableKeywords
     *
     * @param $keywords
     */
    public function testGetBiddableKeyword($keywords)
    {
        $keyword  = array_shift($keywords);
        $response = self::$client->getBiddableKeyword($keyword['keywordId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListBiddableKeywordsEx
     *
     * @param $keywords
     */
    public function testGetBiddableKeywordEx($keywords)
    {
        $keyword  = array_shift($keywords);
        $response = self::$client->getBiddableKeywordEx($keyword['keywordId']);
        $this->assertSuccessResponse($response);
    }

    public function testCreateBiddableKeywords()
    {
        $data = array(
            array(
                'campaignId'  => 1,
                'adGroupId'   => 1,
                'keywordText' => 'Test text',
                'matchType'   => 'exact',
                'state'       => 'enabled',
            )
        );

        $response = self::$client->createBiddableKeywords($data);
        $this->assertSuccessResponse($response, 207);
    }

    /**
     * @depends testListBiddableKeywords
     *
     * @param $keywords
     */
    public function testUpdateCreateBiddableKeywords($keywords)
    {
        $keyword = array_shift($keywords);
        $data    = array(
            array(
                'keywordId'   => $keyword['keywordId'],
                'campaignId'  => 1,
                'adGroupId'   => 1,
                'keywordText' => 'Test text',
                'matchType'   => 'exact',
                'state'       => 'enabled',
            )
        );

        $response = self::$client->updateBiddableKeywords($data);
        $this->assertSuccessResponse($response, 207);
    }

    /**
     * @depends testListBiddableKeywords
     *
     * @param $keywords
     */
    public function testArchiveBiddableKeyword($keywords)
    {
        $response = self::$client->archiveBiddableKeyword('test');
        $this->assertFalse($response['success']);
        $this->assertSame(404, $response['code']);
    }

    public function testListNegativeKeywords()
    {
        $data     = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );
        $response = self::$client->listNegativeKeywords($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    public function testListNegativeKeywordsEx()
    {
        $data     = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );
        $response = self::$client->listNegativeKeywordsEx($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }


    /**
     * @depends testListNegativeKeywords
     *
     * @param $keywords
     */
    public function testGetNegativeKeyword($keywords)
    {
        $keyword  = array_shift($keywords);
        $response = self::$client->getNegativeKeyword($keyword['keywordId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListNegativeKeywordsEx
     *
     * @param $keywords
     */
    public function testGetNegativeKeywordEx($keywords)
    {
        $keyword  = array_shift($keywords);
        $response = self::$client->getNegativeKeywordEx($keyword['keywordId']);
        $this->assertSuccessResponse($response);
    }

    public function testCreateNegativeKeywords()
    {
        $data = array(
            array(
                'campaignId'  => 1,
                'adGroupId'   => 1,
                'keywordText' => 'Test text',
                'matchType'   => 'exact',
                'state'       => 'enabled',
            )
        );

        $response = self::$client->createNegativeKeywords($data);
        $this->assertSuccessResponse($response, 207);
    }

    /**
     * @depends testListNegativeKeywords
     *
     * @param $keywords
     */
    public function testUpdateNegativeKeywords($keywords)
    {
        $keyword = array_shift($keywords);
        $data    = array(
            array(
                'keywordId'   => $keyword['keywordId'],
                'campaignId'  => 1,
                'adGroupId'   => 1,
                'keywordText' => 'Test text',
                'matchType'   => 'exact',
                'state'       => 'enabled',
            )
        );


        $response = self::$client->updateNegativeKeywords($data);
        $this->assertSuccessResponse($response, 207);
    }

    public function testArchiveNegativeKeyword()
    {
        $response = self::$client->archiveNegativeKeyword("test");
        $this->assertFalse($response['success']);
        $this->assertSame(404, $response['code']);
    }

    /**
     * @return mixed
     */
    public function testListCampaignNegativeKeywords()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listCampaignNegativeKeywords($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @return mixed
     */
    public function testListCampaignNegativeKeywordsEx()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listCampaignNegativeKeywordsEx($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @depends testListCampaignNegativeKeywords
     *
     * @param $keywords
     */
    public function testGetCampaignNegativeKeyword($keywords)
    {
        $keyword  = array_shift($keywords);
        $response = self::$client->getCampaignNegativeKeyword($keyword['keywordId']);
        $this->assertSuccessResponse($response);
    }


    /**
     * @depends testListCampaignNegativeKeywords
     *
     * @param $keywords
     */
    public function testGetCampaignNegativeKeywordEx($keywords)
    {
        $keyword  = array_shift($keywords);
        $response = self::$client->getCampaignNegativeKeywordEx($keyword['keywordId']);
        $this->assertSuccessResponse($response);
    }

    public function testCreateCampaignNegativeKeywords()
    {
        $data = array(
            array(
                'campaignId'  => 1,
                'keywordText' => 'Test text',
                'matchType'   => 'exact',
                'state'       => 'enabled',
            )
        );

        $response = self::$client->createCampaignNegativeKeywords($data);
        $this->assertSuccessResponse($response, 207);
    }

    /**
     * @depends testListCampaignNegativeKeywords
     *
     * @param $keywords
     */
    public function testUpdateCampaignNegativeKeywords($keywords)
    {
        $keyword = array_shift($keywords);
        $data    = array(
            array(
                'keywordId'   => $keyword['keywordId'],
                'campaignId'  => 1,
                'keywordText' => 'Test text',
                'matchType'   => 'exact',
                'state'       => 'enabled',
            )
        );


        $response = self::$client->updateCampaignNegativeKeywords($data);
        $this->assertSuccessResponse($response, 207);
    }

    public function testRemoveCampaignNegativeKeyword()
    {
        $response = self::$client->removeCampaignNegativeKeyword("test");
        $this->assertFalse($response['success']);
        $this->assertSame(404, $response['code']);
    }

    public function testListProductAds()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listProductAds($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    public function testListProductAdsEx()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listProductAdsEx($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @depends testListProductAds
     *
     * @param $products
     */
    public function testGetProductAd($products)
    {
        $product = array_shift($products);

        $response = self::$client->getProductAd($product['adId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListProductAdsEx
     *
     * @param $products
     */
    public function testGetProductAdEx($products)
    {
        $product = array_shift($products);

        $response = self::$client->getProductAdEx($product['adId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListAdGroups
     *
     * @param $adGropups
     */
    public function testCreateProductAds($adGropups)
    {
        $adGropup = array_shift($adGropups);

        $data = array(
            array(
                'campaignId' => 1,
                'adGroupId'  => $adGropup['adGroupId'],
                'sku'        => 'test SKU',
                'state'      => 'enabled',
            )
        );

        $response = self::$client->createProductAds($data);
        $this->assertSuccessResponse($response, 207);
    }

    /**
     * @depends testListAdGroups
     *
     * @param $adGropups
     */
    public function testUpdateProductAds($adGropups)
    {
        $adGropup = array_shift($adGropups);

        $data = array(
            array(
                'campaignId' => 1,
                'adGroupId'  => $adGropup['adGroupId'],
                'sku'        => 'test SKU',
                'state'      => 'enabled',
            )
        );

        $response = self::$client->updateProductAds($data);
        $this->assertSuccessResponse($response, 207);
    }

    public function testArchiveProductAd()
    {
        $response = self::$client->archiveProductAd("test");
        $this->assertFalse($response['success']);
        $this->assertSame(404, $response['code']);

    }

    /**
     * @depends testListAdGroups
     * @depends testListCampaigns
     *
     * @param $adGropups
     * @param $campaings
     */
    public function testCreateTargetingClauses($adGropups, $campaings)
    {
        $adGroup  = array_shift($adGropups);
        $campaign = array_shift($campaings);

        $data = array(
            array(
                'adGroupId'      => $adGroup['adGroupId'],
                'campaignId'     => $campaign['campaignId'],
                'state'          => 'enabled',
                'expression'     => [
                    [
                        "type"  => "asinSameAs",
                        "value" => "Test Value"
                    ],
                ],
                'expressionType' => 'auto',
                'bid'            => 0.02,
            )
        );

        $response = self::$client->createTargetingClauses($data);
        $this->assertSuccessResponse($response, 207);
    }

    public function testListTargetingClauses()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listTargetingClauses($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    public function testListTargetingClausesEx()
    {
        $data = array(
            'startIndex'  => 0,
            'count'       => 1,
            'stateFilter' => 'enabled',
        );

        $response = self::$client->listTargetingClausesEx($data);
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    public function testGetTargetingClause()
    {
        $response = self::$client->getTargetingClause('test');
        $this->assertNotFoundResponse($response);
    }

    public function testGetTargetingClauseEx()
    {
        $response = self::$client->getTargetingClauseEx('test');
        $this->assertNotFoundResponse($response);
    }

    public function testUpdateTargetingClauses()
    {
        $response = self::$client->updateTargetingClauses([[]]);
        $this->assertInvalidRequestResponse($response);
    }

    public function testArchiveTargetingClause()
    {
        $response = self::$client->archiveTargetingClause("test");
        $this->assertNotFoundResponse($response);
    }

    public function testCreateTargetRecommendations()
    {
        $data = [
            'pageSize'   => 1,
            'pageNumber' => 1,
            'asins'      => ['asin'],
        ];

        $response = self::$client->createTargetRecommendations($data);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListAdGroups
     *
     * @param $groups
     */
    public function testGetAdGroupBidRecommendations($groups)
    {
        $group = array_shift($groups);

        $response = self::$client->getAdGroupBidRecommendations($group['adGroupId']);

        $this->assertFalse($response['success']);
        $this->assertSame(400, $response['code']);
    }

    /**
     * @depends testListBiddableKeywords
     *
     * @param $keywords
     */
    public function testGetKeywordBidRecommendations($keywords)
    {
        $keyword = array_shift($keywords);

        $response = self::$client->getKeywordBidRecommendations($keyword['keywordId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListBiddableKeywords
     *
     * @param $keywords
     */
    public function testBulkGetKeywordBidRecommendations($keywords)
    {
        $keyword = array_shift($keywords);

        $response = self::$client->getKeywordBidRecommendations($keyword['keywordId']);
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListAdGroups
     *
     * @param $groups
     */
    public function testGetAdGroupKeywordSuggestions($groups)
    {
        $group = array_shift($groups);

        $response = self::$client->getAdGroupKeywordSuggestions(
            array("adGroupId" => $group['adGroupId']));
        $this->assertSuccessResponse($response);
    }

    /**
     * @depends testListAdGroups
     *
     * @param $groups
     */
    public function testGetAdGroupKeywordSuggestionsEx($groups)
    {
        $group = array_shift($groups);

        $response = self::$client->getAdGroupKeywordSuggestionsEx(
            array("adGroupId" => $group['adGroupId']));
        $this->assertSuccessResponse($response);
    }

    public function testGetAsinKeywordSuggestions()
    {
        $response = self::$client->getAsinKeywordSuggestions(
            array("asin" => 12345));

        $this->assertSame(400, $response['code']);
        $this->assertFalse($response['success']);
    }

    public function testBulkGetAsinKeywordSuggestions()
    {
        $response = self::$client->bulkGetAsinKeywordSuggestions(
            array("asins" => array("ASIN1", "ASIN2")));

        $this->assertSame(400, $response['code']);
        $this->assertFalse($response['success']);
    }

    public function testRequestSnapshot()
    {
        $response = self::$client->requestSnapshot('campaigns', array('stateFilter' => 'enabled'));
        $this->assertSuccessResponse($response);

        $response = self::$client->requestSnapshot('keywords', array('stateFilter' => 'enabled'));
        $this->assertSuccessResponse($response);

        return json_decode($response['response'], true);
    }

    /**
     * @depends testRequestSnapshot
     *
     * @param $snapshot
     */
    public function testGetSnapshot($snapshot)
    {
        $response = self::$client->getSnapshot($snapshot['snapshotId']);
        $this->assertSuccessResponse($response);
    }

    public function testRequestReport()
    {
        $response = self::$client->requestReport("productAds");
        $this->assertFalse($response['success']);
        $this->assertSame(400, $response['code']);
    }

    public function testGetReport()
    {
        $response = self::$client->getReport("test");
        $this->assertFalse($response['success']);
        $this->assertSame(404, $response['code']);
    }

    private function assertSuccessResponse($actualResponse, $successCode = 200)
    {
        $successResponse = array(
            "code"     => $successCode,
            "success"  => true,
            "response" => "",
            "requestId" => "",
        );

        foreach ($successResponse as $responseItemName => $responseItemValue) {
            $this->assertArrayHasKey($responseItemName, $actualResponse);
        }

        $this->assertSame($successCode, $actualResponse['code']);
        $this->assertEquals(true, $actualResponse['success']);
    }

    private function assertNotFoundResponse($actualResponse, $code = 404)
    {
        $response = array(
            "code"    => $code,
            "success" => false,
        );

        foreach ($response as $responseItemName => $responseItemValue) {
            $this->assertArrayHasKey($responseItemName, $actualResponse);
        }

        $this->assertSame($code, $actualResponse['code']);
        $this->assertEquals(false, $actualResponse['success']);
    }

    private function assertInvalidRequestResponse($actualResponse, $code = 422)
    {
        $response = array(
            "code"    => $code,
            "success" => false,
        );

        foreach ($response as $responseItemName => $responseItemValue) {
            $this->assertArrayHasKey($responseItemName, $actualResponse);
        }

        $this->assertSame($code, $actualResponse['code']);
        $this->assertEquals(false, $actualResponse['success']);
    }


}
