# Manual Test

Manual testing involves an HL7-like message type; a client to generate various
length MLLP encapsulated messages and handle acknowledgements; and simulations
of the forward transports (currently only HTTP is available).

- A simulated HTTP endpoint is provided as `HttpEndpoint.php`.  It understands
  the HL7-like messages and responds to valid requests with appropriate
  acknowledgements.

- MLLP encapsulated messages are generated and sent by `MllpClient.php`. It
  puts pseudo-random length messages on the wire once every second. It uses the
  ACKs to verify that the messages are received fully and correctly.

- A test `config.yml` is provided for `bin/bridge`.


## Test Http Transport

Start the endpoint:-

    $ php test/HttpEndpoint.php &
    [HTTP] Listening on 127.0.0.1:8910


Start the bridge with the `http` transport and using the test config (and
DEBUG):-

    $ php bin/bridge -vvv -c test/config.yml -t http &
    [BRIDGE] Listening on 127.0.0.1:2575


Run the client to start the test:-

    $ php test/MllpClient.php
    [MLLP] Client (01) is starting. Press Ctrl C to stop.
    [MLLP] Send Message |01|0001|... 196,616 bytes.
    [HTTP] Got Request from Client 01 [mid: 0001].
    [MLLP] Receive |ACK|01|0001|196616|
    [MLLP] Send Message |01|0002|... 262,151 bytes.
    [HTTP] Got Request from Client 01 [mid: 0002].
    [MLLP] Receive |ACK|01|0002|262151|
    ^C
    $

Multiple clients may be started:-

    php test/MllpClient.php --help
    Usage: test/MllpClient.php [clientID]
    (where 0 < clientID < 100)
