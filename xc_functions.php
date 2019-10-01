<?php

ini_set('display_errors', 'Off');
error_reporting(E_ALL);

class Functions {

    // database connection and table name
    /**
     *
     * @var type 
     */
    private $conn;

    // constructor with $db as database connection
    public function __construct() {
        include_once 'xc_config.php';
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Check if user has valid username and password
    /**
     * 
     * @param type $username
     * @param type $password
     * @return boolean
     */
    public function validateUser($username, $password) {
        $sql = "SELECT username, password "
                . "FROM users "
                . "WHERE username='$username' "
                . "AND password='$password' "
                . "AND enabled=1";
        $query = $this->conn->prepare($sql);
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->bindParam(':password', $password, PDO::PARAM_STR);
        $query->execute();
        $results = $query->fetch(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    // Check Login and get user' info.
    /**
     * 
     * @param type $username
     * @param type $password
     * @return string
     */
    public function getUserInfo($username, $password) {

        if ($this->validateUser($username, $password)) {
            $sql = "SELECT username, password, exp_date, enabled, is_trial, max_connections, created_at "
                    . "FROM users "
                    . "WHERE username=:username "
                    . "AND password=:password "
                    . "LIMIT 1";

            $query = $this->conn->prepare($sql);
            $query->bindParam(':username', $username, PDO::PARAM_STR);
            $query->bindParam(':password', $password, PDO::PARAM_STR);
            $query->execute();
            $results = $query->fetch(PDO::FETCH_OBJ);
            $status = "Inactive";
            $auth = 0;

            if ($results->enabled == "1") {
                $status = "Active";
                $auth = 1;
            } else {
                $status = "Inactive";
                $auth = 0;
            }

            $strem_outputs = array("m3u8", "ts");
            $user_info = array(
                "username" => $results->username,
                "password" => $results->password,
                "message" => "",
                "auth" => $auth,
                "status" => $status,
                "exp_date" => $results->exp_date,
                "is_trial" => $results->is_trial,
                "active_cons" => "0",
                "created_at" => $results->created_at,
                "max_connections" => $results->max_connections,
                "allowed_output_formats" => $strem_outputs,
            );
            return $user_info;
        } else {

            $user_info = array(
                "auth" => 0,
                "status" => "Inactive",
            );
            return $user_info;
        }
    }

    // Get Servers Details
    /**
     * 
     * @return string
     */
    public function getServerInfo() {
        $sql1 = "SELECT ss.server_name, ss.http_broadcast_port, se.default_timezone "
                . "FROM streaming_servers ss, settings se";
        $query1 = $this->conn->prepare($sql1);
        $query1->execute();
        $results1 = $query1->fetch(PDO::FETCH_OBJ);

        $server_info = array(
            "url" => $results1->server_name,
            "port" => $results1->http_broadcast_port,
            "https_port" => "25463",
            "server_protocol" => "http",
            "rtmp_port" => "25462",
            "timezone" => $results1->default_timezone,
            "timestamp_now" => time(),
            "time_now" => date('y-m-d H:m:s'),
        );
        return $server_info;
    }

    // Get Categories
    /**
     * 
     * @param type $username
     * @param type $password
     * @param type $type
     * @return int
     */
    public function getCategories($username, $password, $type) {
        $categories = array();
        if ($this->validateUser($username, $password)) {
            $sql = "SELECT id as category_id, category_name "
                    . "FROM stream_categories "
                    . "WHERE category_type='$type'";
            $query = $this->conn->prepare($sql);
            $query->execute();

            $results = $query->fetchAll(PDO::FETCH_OBJ);

            $categories = array();
            if ($query->rowCount() > 0) {
                foreach ($results as $result) {
                    $categories[] = array(
                        "category_id" => (int) $result->category_id,
                        "category_name" => $result->category_name,
                        "parent_id" => 0);
                }
            }
            return $categories;
        } else {
            return $categories;
        }
    }

    /**
     * 
     * @param type $username
     * @param type $password
     * @param type $type
     * @return boolean
     */
    public function getLiveStreams($username, $password, $type) {

        $user_sql = "SELECT username, password, bouquet "
                . "FROM users "
                . "WHERE username='$username' "
                . "AND password='$password' "
                . "AND enabled=1";
        $user_query = $this->conn->prepare($user_sql);
        $user_query->bindParam(':username', $username, PDO::PARAM_STR);
        $user_query->bindParam(':password', $password, PDO::PARAM_STR);
        $user_query->execute();
        $user_results = $user_query->fetch(PDO::FETCH_OBJ);
        if ($user_query->rowCount() > 0) {

            $bouquet = $user_results->bouquet;
            $bouquet = str_replace("[", "", $bouquet);
            $bouquet = str_replace("]", "", $bouquet);
            $b_channels = $this->getBouquetStreams($bouquet);
            $streams = array();

            $sql = "SELECT s.id, s.stream_display_name, s.stream_icon, s.added, s.category_id, s.channel_id, s.custom_sid,s.type , st.type_name, st.type_key FROM streams s "
                    . "INNER JOIN  streams_types  st "
                    . "ON st.type_id= s.type "
                    . "AND st.type_output='live' OR st.type_output='created_live' "
                    . "WHERE  id in ($b_channels)";

            $query = $this->conn->prepare($sql);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);

            if ($query->rowCount() > 0) {
                foreach ($results as $result) {
                    $streams[] = array(
                        "num" => (int) $result->id,
                        "name" => $result->stream_display_name,
                        "stream_type" => "live",
                        "stream_id" => (int) $result->id,
                        "stream_icon" => $result->stream_icon,
                        "epg_channel_id" => $result->channel_id ?: '',
                        "added" => $result->added,
                        "category_id" => $result->category_id,
                        "custom_sid" => "",
                        "tv_archive" => 0,
                        "direct_source" => "",
                        "tv_archive_duration" => 0
                    );
                }
            }
            return $streams;
            return true;
        } else {
            return false;
        }



        if ($this->validateUser($username, $password)) {
            
        } else {
            return $streams;
        }
    }

    // Get Movies list
    /**
     * 
     * @param type $username
     * @param type $password
     * @param type $type
     * @return boolean
     */
    public function getMovieStreams($username, $password, $type) {
        
        $user_sql = "SELECT username, password, bouquet "
                . "FROM users "
                . "WHERE username='$username' "
                . "AND password='$password' "
                . "AND enabled=1";

        $user_query = $this->conn->prepare($user_sql);
        $user_query->bindParam(':username', $username, PDO::PARAM_STR);
        $user_query->bindParam(':password', $password, PDO::PARAM_STR);
        $user_query->execute();
        $user_results = $user_query->fetch(PDO::FETCH_OBJ);
        if ($user_query->rowCount() > 0) {
            $bouquet = $user_results->bouquet;
            $bouquet = str_replace("[", "", $bouquet);
            $bouquet = str_replace("]", "", $bouquet);

            $b_channels = $this->getBouquetStreams($bouquet);
            $streams = array();
            $sql = "SELECT s.id, s.stream_display_name, s.stream_icon, s.added, s.category_id, s.target_container_id, s.custom_sid,s.type , st.type_name, st.type_key "
                    . "FROM streams s "
                    . "INNER JOIN  streams_types  st "
                    . "ON st.type_id= s.type "
                    . "AND st.type_output='movie' "
                    . "WHERE id in ($b_channels)";
            
            $query = $this->conn->prepare($sql);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);

            if ($query->rowCount() > 0) {
                foreach ($results as $result) {
                    $streams[] = array(
                        "num" => (int) $result->id,
                        "name" => $result->stream_display_name,
                        "stream_type" => "movie",
                        "stream_id" => (int) $result->id,
                        "stream_icon" => $result->stream_icon,
                        "rating" => "",
                        "rating_5based" => 0,
                        "added" => $result->added,
                        "category_id" => $result->category_id,
                        "container_extension" => $this->getMovieContainerName($result->target_container_id),
                        "custom_sid" => "",
                        "direct_source" => ""
                    );
                }
            }
            return $streams;
            return true;
        } else {
            return false;
        }
    }

    // Get Movie's Info by id
    /**
     * 
     * @param type $username
     * @param type $password
     * @param type $void_id
     * @return string
     */
    public function getMovieInfo($username, $password, $void_id) {
        if ($this->validateUser($username, $password)) {
            $info = "";

            $sql = "SELECT movie_propeties as info "
                    . "FROM streams "
                    . "WHERE id='$void_id'";

            $query = $this->conn->prepare($sql);
            $query->execute();
            $results = $query->fetch(PDO::FETCH_OBJ);

            $obj = json_decode($results->info, true);

            $movie_info = array(
                "movie_image" => $obj->{'movie_image'},
                "backdrop_path" => array(),
                "youtube_trailer" => "",
                "genre" => $obj->{'genre'} ?: '',
                "plot" => $obj->{'plot'} ?: '',
                "cast" => $obj->{'cast'} ?: '',
                "rating" => $obj->{'rating'} ?: '',
                "director" => $obj->{'director'} ?: '',
                "releasedate" => $obj->{'releasedate'} ?: '',
                "tmdb_id" => "",
                "duration_secs" => 0,
                "duration" => "00:01:00",
            );
            return $movie_info;
        } else {
            return "Movie info not available";
        }
    }

    /**
     * 
     * @param type $void_id
     * @return type
     */
    public function getMovieData($void_id) {
        $sql = "SELECT id, stream_display_name, added, category_id, target_container_id, custom_sid "
                . "FROM streams "
                . "WHERE id='$void_id'";
        $query = $this->conn->prepare($sql);
        $query->execute();
        $results = $query->fetch(PDO::FETCH_OBJ);

        $movie_info = array(
            "stream_id" => (int) $results->id,
            "name" => $results->stream_display_name,
            "added" => $results->added,
            "category_id" => $results->category_id,
            "container_extension" => $this->getMovieContainerName($results->target_container_id),
            "custom_sid" => $results->custom_sid,
            "direct_source" => "",
        );
        return $movie_info;
    }

    // Get Movie file' extension
    /**
     * 
     * @param type $c_id
     * @return type
     */
    public function getMovieContainerName($c_id) {
        $sql = "SELECT container_extension "
                . "FROM movie_containers "
                . "WHERE container_id='$c_id'";
        $query = $this->conn->prepare($sql);
        $query->execute();
        $results = $query->fetch(PDO::FETCH_OBJ);
        return $results->container_extension;
    }

    // Get channels list from Bouquet
    /**
     * 
     * @param type $bouquet_ids
     * @return string
     */
    public function getBouquetStreams($bouquet_ids) {
        $bouquet_channels = "0";
        $bouquet_ch = "";
        $sql = "SELECT * FROM bouquets WHERE id in ($bouquet_ids)";
        $query = $this->conn->prepare($sql);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_OBJ);
        $cnt = 1;
        if ($query->rowCount() > 0) {
            foreach ($results as $result) {
                $bouquet_ch = $result->bouquet_channels;
                $bouquet_ch = str_replace("[", "", $bouquet_ch);
                $bouquet_ch = str_replace("]", "", $bouquet_ch);

                if($bouquet_channels == "0"){
                    $bouquet_channels = $bouquet_ch;
                }else{
                    $bouquet_channels = $bouquet_channels .",". $bouquet_ch;
                }
                $cnt++;
            }
        }
        return $bouquet_channels;
    }

}
?>

