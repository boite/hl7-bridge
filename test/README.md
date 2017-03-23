# Manual Test

- A dummy HTTP endpoint is provided `HttpEndpoint.php`. It simply responds to
  any request with "`|ACK|\r`".

- MLLP encapsulated messages are generated and sent by `MllpClient.php`. The
  messages, two tiny and one very large, are plain-text (not HL7).

- A test `config.yml` is provided. It contains the end point url
  `http://127.0.0.1:8910/` and the address of a dummy DNS resolver (which will
  not be contacted during the test).


Start the endpoint:-

    $ php test/HttpEndpoint.php &
    [HTTP] Listening on 127.0.0.1:8910


Start the bridge with the test config (and DEBUG):-

    $ php bin/bridge -vvv -c test/config.yml &
    [BRIDGE] Listening on 127.0.0.1:2575


Run the client to start the test:-

    $ php test/MllpClient.php
    [MLLP] Client is starting. Press Ctrl C to stop.
    [MLLP] Send Message.
    [MLLP] Send Message.
    [MLLP] Send Message.
    [HTTP] got request.
    [HTTP] got request.
    [MLLP] Receive |ACK|
    |ACK|
    [HTTP] got request.
    [MLLP] Receive |ACK|
    ^C
    $


Shut down:-

    $ fg
    ^C
    $ fg
    ^C
    $
