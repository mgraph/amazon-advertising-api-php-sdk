<?php
namespace AmazonAdvertisingApi;

class Regions
{
    public $endpoints = array(
        "na" => array(
            "prod"     => "advertising-api.amazon.com",
            "tokenUrl" => "api.amazon.com/auth/o2/token"),
        "eu" => array(
            "prod"     => "advertising-api-eu.amazon.com",
            "tokenUrl" => "api.amazon.com/auth/o2/token"
        ),
        "fe" => array(
            "prod"     => "advertising-api-fe.amazon.com",
            "tokenUrl" => "api.amazon.com/auth/o2/token"
        )
    );
}
