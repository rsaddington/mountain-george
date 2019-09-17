<?php


namespace Drupal\gd_regions;


class GeoDetection {
  public function test($ip, $cidr) {
    list ($net, $mask) = explode ('/', $cidr);

    $ip_net = ip2long ($net);
    $ip_mask = ~((1 << (32 - $mask)) - 1);

    $ip_ip = ip2long ($ip);

    return (($ip_ip & $ip_mask) == ($ip_net & $ip_mask));
  }
}