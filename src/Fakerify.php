<?php

namespace Sxtnmedia\Wp;

use WP_User_Query;
use wpdb;

class Fakerify
{
    protected $faker;
    protected $wpdb;
    public $perPage = 1000;

    public function __construct($faker = null)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->faker = $faker ?: \Faker\Factory::create('pl_PL');

        // Turn off sending notification emails after changing email/password
        add_filter('send_email_change_email', '__return_false');
        add_filter('password_change_email', '__return_false');
    }

    /**
     * @param array $queryArgs Additional WP_User_Query args. 
     *  Default: ['role__not_in' => ['Administrator']]
     */
    public function run($queryArgs = [])
    {
        $timer = microtime(true);

        $perPage = 1000;
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->users} WHERE ID <= 2000");
        $pages = ceil($count / $perPage);

        for ($page = 1; $page <= $pages; $page++) {
            $this->replace($this->getUsers($page, $queryArgs));
        }

        echo "Took " . round(microtime(true) - $timer, 3) . "s";
    }

    /**
     * @param int $page
     * @param array $queryArgs Additional WP_User_Query args
     * @return array<WP_User> $users 
     */
    protected function getUsers($page = 1, $queryArgs = [])
    {
        $usersQuery = new WP_User_Query(array_merge([
            'number' => $this->perPage,
            'paged' => $page,
            'role__not_in' => ['Administrator'],
        ], $queryArgs));

        return $usersQuery->get_results();
    }

    /**
     * @param array $users
     */
    protected function replace(array $users)
    {
        foreach ($users as $user) {
            $userName = $this->faker->userName;
            $firstName = $this->faker->firstName;
            $lastName = $this->faker->lastName;
            $email = $this->faker->unique(false, 50000)->safeEmail;
            $address = $this->faker->streetAddress;
            $company = $this->faker->company;
            $city = $this->faker->city;
            $postcode = $this->faker->postcode;
            $country = $this->faker->countryCode;
            $phone = $this->faker->phoneNumber;

            $this->wpdb->update($this->wpdb->users, [
                'user_nicename' => $userName,
                'user_login' => $userName,
                'display_name' => $userName,
                'user_email' => $email,
            ], [
                'ID' => $user->ID
            ]);

            $metaToUpdate = [
                'nickname' => $userName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'billing_first_name' => $firstName,
                'billing_last_name' => $lastName,
                'billing_address_1' => $address,
                'billing_city' => $city,
                'billing_postcode' => $postcode,
                'billing_country' => $country,
                'billing_email' => $email,
                'billing_phone' => $phone,
                'shipping_first_name' => $firstName,
                'shipping_last_name' => $lastName,
                'shipping_address_1' => $address,
                'shipping_city' => $city,
                'shipping_postcode' => $postcode,
                'shipping_country' => $country,
                'billing_company' => $company,
                'shipping_company' => $company,
            ];

            $meta = array_filter(get_user_meta($user->ID), function ($v) {
                return !empty($v) && strlen($v[0]);
            });

            foreach ($metaToUpdate as $metaKey => $metaItem) {
                if (in_array($metaKey, $meta)) {

                    $this->wpdb->update($this->wpdb->usermeta, [
                        'meta_value' => $metaItem,
                    ], [
                        'meta_key' => $metaKey,
                        'user_id' => $user->ID,
                    ]);
                }
            }
        }
    }
}
