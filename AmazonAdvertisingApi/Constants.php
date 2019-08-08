<?php


namespace AmazonAdvertisingApi;


class Constants
{
    public const STATE_ENABLED  = 'enabled';
    public const STATE_PAUSED   = 'paused';
    public const STATE_ARCHIVED = 'archived';
    public const STATE_DELETED  = 'deleted';

    public const MATCH_TYPE_NEGATIVE_EXACT  = 'negativeExact';
    public const MATCH_TYPE_NEGATIVE_PHRASE = 'negativePhrase';

    public const CAMPAIGN_PORTFOLIO_ID           = 'portfolioId';
    public const CAMPAIGN_ID                     = 'campaignId';
    public const CAMPAIGN_NAME                   = 'name';
    public const CAMPAIGN_TARGETING_TYPE         = 'targetingType';
    public const CAMPAIGN_STATE                  = 'state';
    public const CAMPAIGN_DAILY_BUDGET           = 'dailyBudget';
    public const CAMPAIGN_START_DATE             = 'startDate';
    public const CAMPAIGN_END_DATE               = 'endDate';
    public const CAMPAIGN_PREMIUM_BID_ADJUSTMENT = 'premiumBidAdjustment';
    public const CAMPAIGN_PLACEMENT              = 'placement';
    public const CAMPAIGN_CREATION_DATE          = 'creationDate';
    public const CAMPAIGN_LAST_UPDATE_DATE       = 'lastUpdatedDate';
    public const CAMPAIGN_SERVING_STATUS         = 'servingStatus';

    public const ADGROUP_ID               = 'adGroupId';
    public const ADGROUP_NAME             = 'name';
    public const ADGROUP_CAMPAIGN_ID      = 'campaignId';
    public const ADGROUP_DEFAULT_BID      = 'defaultBid';
    public const ADGROUP_STATE            = 'state';
    public const ADGROUP_CREATION_DATE    = 'creationDate';
    public const ADGROUP_LAST_UPDATE_DATE = 'lastUpdatedDate';
    public const ADGROUP_SERVING_STATIS   = 'servingStatus';

    public const AD_ID               = 'adId';
    public const AD_CAMPAIGN_ID      = 'campaignId';
    public const AD_ADGROUP_ID       = 'adGroupId';
    public const AD_SKU              = 'sku';
    public const AD_ASIN             = 'asin';
    public const AD_STATE            = 'state';
    public const AD_CREATION_DATE    = 'creationDate';
    public const AD_LAST_UPDATE_DATE = 'lastUpdatedDate';
    public const AD_SERVING_STATUS   = 'servingStatus';

    public const KEYWORD_ID                = 'keywordId';
    public const KEYWORD_CAMPAIGN_ID       = 'campaignId';
    public const KEYWORD_ADGROUP_ID        = 'adGroupId';
    public const KEYWORD_STATE             = 'state';
    public const KEYWORD_KEYWORD_TEXT      = 'keywordText';
    public const KEYWORD_MATCH_TYPE        = 'matchType';
    public const KEYWORD_MATCH_TYPE_EXACT  = 'exact';
    public const KEYWORD_MATCH_TYPE_PHRASE = 'phrase';
    public const KEYWORD_MATCH_TYPE_BROAD  = 'broad';
    public const KEYWORD_BID               = 'bid';
    public const KEYWORD_CREATION_DATE     = 'creationDate';
    public const KEYWORD_LAST_UPDATE_DATE  = 'lastUpdatedDate';
    public const KEYWORD_SERVING_STATUS    = 'servingStatus';

    public const TARGET_ID                     = 'targetId';
    public const TARGET_CAMPAIGN_ID            = 'campaignId';
    public const TARGET_ADGROUP_ID             = 'adGroupId';
    public const TARGET_STATE                  = 'state';
    public const TARGET_EXPRESSION             = 'expression';
    public const TARGET_EXPRESSION_TYPE        = 'expressionType';
    public const TARGET_EXPRESSION_TYPE_AUTO   = 'auto';
    public const TARGET_EXPRESSION_TYPE_MANUAL = 'manual';
    public const TARGET_BID                    = 'bid';
    public const TARGET_CREATION_DATE          = 'creationDate';
    public const TARGET_LAST_UPDATE_DATE       = 'lastUpdatedDate';
    public const TARGET_SERVING_STATUS         = 'servingStatus';

    public const FILTER_CAMPAIGN_ID = 'campaignIdFilter';
    public const FILTER_ADGROUP_ID  = 'adGroupIdFilter';
    public const FILTER_STATE       = 'stateFilter';
    public const FILTER_STARTINDEX  = 'startIndex';
}
