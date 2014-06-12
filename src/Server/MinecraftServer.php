<?php
namespace PublicUHC\MinecraftAuth\Server;

use UnexpectedValueException;

class MinecraftServer {

    private $server;
    private $connections = [];

    public function __construct($host = '0.0.0.0', $port = 25565)
    {
        $this->server = stream_socket_server("tcp://$host:$port", $errNo, $errMsg);

        if ($this->server === false) {
            throw new UnexpectedValueException('Could not bind to host socket: ' . $errMsg);
        }
    }

    /**
     * Return the client for the socket provided. If none exists one is created and returned
     *
     * @param $socket resource the socket connection
     * @return Client
     */
    public function getClientForSocket($socket)
    {
        /** @var $connection Client */
        foreach($this->connections as $connection) {
            if($connection->getConnection() == $socket) {
                return $connection;
            }
        }

        $client = new Client($socket);
        $this->connections[] = $client;
        return $client;
    }

    /**
     * Removes the connection for the socket provided. Also closes the socket if found
     *
     * @param $socket
     */
    public function removeClientForSocket($socket)
    {
        for($i = 0; $i<count($this->connections); $i++) {
            /** @var $connection Client */
            $connection = $this->connections[$i];
            if($connection->getConnection() == $socket) {
                $connection->close();
                unset($this->connections[$i]);
                $this->connections = array_values($this->connections);
                return;
            }
        }
    }

    public function start()
    {
        while(true) {

            //list of all the connections we want to check this round
            $read = [];

            //add the base server socket to check for new connections
            $read[0] = $this->server;

            //add all of the client sockets
            /** @var $connection Client */
            foreach($this->connections as $connection) {
                if($connection->getConnection() != null) {
                    $read[] = $connection->getConnection();
                }
            }

            //check streams for read/write with timeout of 5 seconds
            if(!@stream_select($read, $write, $except, 5)) {
                continue;
            }

            //if the server is in the available list
            if(in_array($this->server, $read)) {
                //attempt to accept a new client
                $new_client = stream_socket_accept($this->server);

                //if new connection
                if ($new_client) {

                    //print remote client information, ip and port number
                    echo 'Connection accepted from ' . stream_socket_get_name($new_client, true) . "\n";

                    //add to our list
                    $this->getClientForSocket($new_client);

                    //output total amount
                    echo "Now there are total ". count($this->connections) . " clients.\n";
                }

                //delete the server socket from the read list
                unset($read[array_search($this->server, $read)]);
            }

            //message from existing client
            foreach($read as $client) {
                $data = @fread($client, 65535);

                echo("data = ".json_encode($data)."\n");

                //if no data disconnect the client
                if(!$data) {
                    //output total amount
                    echo "Now there are total ". count($this->connections) . " clients.\n";
                    $this->removeClientForSocket($client);
                    echo "A client disconnected. Now there are total ". count($this->connections) . " clients.\n";
                    continue;
                }

                //TODO parse data
                @fwrite($client, $data);
            }
        }
    }
}