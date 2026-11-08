<?php

// -----------------------------------------------------------
//  [*] DaemonClientTest — the app's client for bitlitasd
//
//  DaemonClient over real HTTP, against the stand-in node on
//  127.0.0.1:18281 (tests/container/fake-node.php): every
//  call is a JSON-RPC POST to /json_rpc with its params as
//  a JSON object, heights sent as numbers, but for the fees
//  of transactions, a plain JSON POST to /get_transactions,
//  a hundred at a time, the most a restricted node gives;
//  a fee is read from a transaction before RingCT and from
//  one with it. With a login it
//  answers the node's HTTP digest challenge, as bitlitasd's
//  --rpc-login asks. Every way a call can fail — no
//  connection, a refused login, an HTTP error, an answer that
//  is no JSON-RPC, the node's own error, a BUSY status, an
//  answer missing what the caller needs — ends in a
//  DaemonException naming the call and the reason. Numbers
//  beyond PHP's integers stay exact, as text.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Node;

use App\Services\DaemonClient;
use App\Services\DaemonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;
use Tests\Support\Chain;
use Tests\Support\FakeNodeServer;








// -----------------------------------------------------------
// DaemonClientTest
// -----------------------------------------------------------
//
// Each test configures the stand-in node, makes its calls
// with a client of its own and reads the node's request log.
//
//   setUp, tearDown — a fresh node around every test
//   client          — a DaemonClient for the stand-in node
//   failure         — the message a call fails with
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

class DaemonClientTest extends FeatureTestCase
{

    // The login the tests give the node
    private const LOGIN = 'rpcuser:s3cret pass';






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // FeatureTestCase's setup, then the default node.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        FakeNodeServer::reset();
    }






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // The default node again, for whatever runs next.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        FakeNodeServer::reset();
        parent::tearDown();
    }






    // -----------------------------------------------------------
    // testInfoIsTheResultOfGetInfo
    // -----------------------------------------------------------
    //
    // getInfo returns the result of get_info as the node sent
    // it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testInfoIsTheResultOfGetInfo(): void
    {
        $info = $this->client()->getInfo();

        $this->assertSame([2500, 1160, 60, 'OK'], [$info['height'], $info['difficulty'], $info['target'], $info['status']]);
    }






    // -----------------------------------------------------------
    // testCallIsAJsonRpcPost
    // -----------------------------------------------------------
    //
    // One POST to /json_rpc, as JSON, carrying JSON-RPC 2.0's
    // envelope; params is an object even when empty — epee's
    // parser wants one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCallIsAJsonRpcPost(): void
    {
        $this->client()->getInfo();

        $requests = FakeNodeServer::requests();
        $this->assertCount(1, $requests);
        $this->assertSame(['POST', '/json_rpc', 'application/json', null], [$requests[0]['http_method'], $requests[0]['path'], $requests[0]['content_type'], $requests[0]['authorization']]);
        $this->assertSame('{"jsonrpc":"2.0","id":"0","method":"get_info","params":{}}', $requests[0]['raw']);
    }






    // -----------------------------------------------------------
    // testRangeSendsItsHeightsAsNumbers
    // -----------------------------------------------------------
    //
    // getBlockHeadersRange sends start_height and end_height as
    // JSON numbers, whatever it was given, and returns the
    // headers, oldest first.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRangeSendsItsHeightsAsNumbers(): void
    {
        $headers = $this->client()->getBlockHeadersRange('10', 12);

        $this->assertSame([10, 11, 12], array_column($headers, 'height'));
        $this->assertStringContainsString('"params":{"start_height":10,"end_height":12}', FakeNodeServer::requests()[0]['raw']);
    }






    // -----------------------------------------------------------
    // testTrailingSlashInTheAddressIsDropped
    // -----------------------------------------------------------
    //
    // BITLITAS_DAEMON_URL written with a closing slash still
    // reaches /json_rpc, not //json_rpc.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testTrailingSlashInTheAddressIsDropped(): void
    {
        $this->client(null, FakeNodeServer::URL . '/')->getInfo();

        $this->assertSame('/json_rpc', FakeNodeServer::requests()[0]['path']);
    }






    // -----------------------------------------------------------
    // testLoginAnswersTheDigestChallenge
    // -----------------------------------------------------------
    //
    // A node that wants a login answers the first request with
    // a digest challenge; the client answers it and gets its
    // result.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testLoginAnswersTheDigestChallenge(): void
    {
        FakeNodeServer::configure(['login' => self::LOGIN]);

        $info = $this->client(self::LOGIN)->getInfo();

        $this->assertSame(2500, $info['height']);
        $this->assertSame([null, 'Digest'], array_column(FakeNodeServer::requests(), 'authorization'));
    }






    // -----------------------------------------------------------
    // testRefusedLoginNamesTheSetting
    // -----------------------------------------------------------
    //
    // A wrong password, or none where the node wants one,
    // fails naming BITLITAS_DAEMON_LOGIN.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRefusedLoginNamesTheSetting(): void
    {
        FakeNodeServer::configure(['login' => self::LOGIN]);
        $refused = 'get_info: the node refused the login (BITLITAS_DAEMON_LOGIN)';

        $this->assertSame($refused, $this->failure($this->client('rpcuser:wrong')));
        $this->assertSame($refused, $this->failure($this->client()));
    }






    // -----------------------------------------------------------
    // testEmptyLoginIsNoLogin
    // -----------------------------------------------------------
    //
    // BITLITAS_DAEMON_LOGIN set but empty sends no
    // credentials.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testEmptyLoginIsNoLogin(): void
    {
        $this->client('')->getInfo();

        $this->assertSame([null], array_column(FakeNodeServer::requests(), 'authorization'));
    }






    // -----------------------------------------------------------
    // testHttpErrorNamesItsStatus
    // -----------------------------------------------------------
    //
    // A proxy's 502 or a server error comes back as the
    // status the node answered.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testHttpErrorNamesItsStatus(): void
    {
        FakeNodeServer::configure(['http_status' => 502, 'raw_body' => 'Bad Gateway']);

        $this->assertSame('get_info: the node answered HTTP 502', $this->failure($this->client()));
    }






    // -----------------------------------------------------------
    // testAnswerThatIsNoJsonRpcIsRefused
    // -----------------------------------------------------------
    //
    // A 200 whose body is a web page, JSON without a result,
    // or a result that is no object, is refused.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('notJsonRpc')]
    public function testAnswerThatIsNoJsonRpcIsRefused(string $body): void
    {
        FakeNodeServer::configure(['raw_body' => $body]);

        $this->assertSame('get_info: the answer is not a JSON-RPC result', $this->failure($this->client()));
    }






    // -----------------------------------------------------------
    // notJsonRpc
    // -----------------------------------------------------------
    //
    // Bodies a 200 can carry that hold no JSON-RPC result.
    //
    // Used by:
    //   - testAnswerThatIsNoJsonRpcIsRefused (above)
    // -----------------------------------------------------------

    public static function notJsonRpc(): array
    {
        return [
            'a web page'       => ['<html><body>It works!</body></html>'],
            'nothing'          => [''],
            'no result'        => ['{"id":"0","jsonrpc":"2.0"}'],
            'a result of text' => ['{"id":"0","jsonrpc":"2.0","result":"OK"}'],
        ];
    }






    // -----------------------------------------------------------
    // testNodesErrorIsPassedOn
    // -----------------------------------------------------------
    //
    // A JSON-RPC error carries the node's own message: a range
    // past the top, or a method the node does not have.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodesErrorIsPassedOn(): void
    {
        $this->assertSame('get_block_headers_range: Invalid start/end heights.', $this->failure($this->client(), function (DaemonClient $client) {
            $client->getBlockHeadersRange(2499, 2500);
        }));
        $this->assertSame('get_last_block_headers: Method not found', $this->failure($this->client(), function (DaemonClient $client) {
            $client->call('get_last_block_headers');
        }));
    }






    // -----------------------------------------------------------
    // testBusyNodeIsAFailure
    // -----------------------------------------------------------
    //
    // A result whose status is not OK — BUSY while the node
    // loads or syncs — is refused.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testBusyNodeIsAFailure(): void
    {
        FakeNodeServer::configure(['status' => 'BUSY']);

        $this->assertSame('get_info: the node is BUSY', $this->failure($this->client()));
    }






    // -----------------------------------------------------------
    // testInfoWithoutAFigureIsRefused
    // -----------------------------------------------------------
    //
    // get_info without the height, the difficulty or the block
    // time the sync works with is refused, naming what is
    // missing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('infoFigures')]
    public function testInfoWithoutAFigureIsRefused(string $figure): void
    {
        FakeNodeServer::configure(['omit_info' => [$figure]]);

        $this->assertSame("get_info: the answer has no $figure", $this->failure($this->client()));
    }






    // -----------------------------------------------------------
    // infoFigures
    // -----------------------------------------------------------
    //
    // The get_info fields the sync works with.
    //
    // Used by:
    //   - testInfoWithoutAFigureIsRefused (above)
    // -----------------------------------------------------------

    public static function infoFigures(): array
    {
        return [
            'height'     => ['height'],
            'difficulty' => ['difficulty'],
            'target'     => ['target'],
        ];
    }






    // -----------------------------------------------------------
    // testRangeWithoutHeadersIsRefused
    // -----------------------------------------------------------
    //
    // A range answer without its headers list is refused.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRangeWithoutHeadersIsRefused(): void
    {
        FakeNodeServer::configure(['no_headers' => true]);

        $this->assertSame('get_block_headers_range: the answer has no headers', $this->failure($this->client(), function (DaemonClient $client) {
            $client->getBlockHeadersRange(0, 9);
        }));
    }






    // -----------------------------------------------------------
    // testNodeThatIsDownIsNamed
    // -----------------------------------------------------------
    //
    // Nothing listening at the address fails at once, naming
    // the endpoint and curl's reason.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeThatIsDownIsNamed(): void
    {
        $message = $this->failure($this->client(null, 'http://127.0.0.1:1'));

        $this->assertStringStartsWith('cannot reach the node at http://127.0.0.1:1/json_rpc: ', $message);
        $this->assertStringContainsString('Failed to connect to 127.0.0.1', $message);
    }






    // -----------------------------------------------------------
    // testNumbersBeyondPhpsIntegersStayExact
    // -----------------------------------------------------------
    //
    // A number above PHP_INT_MAX — a cumulative difficulty
    // can be one — arrives as its exact digits, not a rounded
    // float.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNumbersBeyondPhpsIntegersStayExact(): void
    {
        FakeNodeServer::configure(['big_numbers' => true]);

        $this->assertSame('18446744073709551615', $this->client()->getInfo()['cumulative_difficulty']);
    }






    // -----------------------------------------------------------
    // testAppsClientComesFromTheSettings
    // -----------------------------------------------------------
    //
    // The container hands out one DaemonClient, built from
    // BITLITAS_DAEMON_URL: it talks to the stand-in node.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testAppsClientComesFromTheSettings(): void
    {
        $client = $this->app->make(DaemonClient::class);

        $this->assertSame($client, $this->app->make(DaemonClient::class));
        $this->assertSame(2500, $client->getInfo()['height']);
        $this->assertCount(1, FakeNodeServer::requests());
    }






    // -----------------------------------------------------------
    // testBlockTransactionsComeFromGetBlock
    // -----------------------------------------------------------
    //
    // getBlockTransactions asks get_block for the height, as
    // a number, and returns the hashes of the block's
    // transactions — none for a block the node lists none for.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testBlockTransactionsComeFromGetBlock(): void
    {
        FakeNodeServer::configure(['height' => 10, 'fees' => [4 => 250000000000, 7 => [100000000000, 20000000000]]]);
        $client = $this->client();

        $this->assertSame([Chain::txHash(4, 0)], $client->getBlockTransactions('4'));
        $this->assertSame([], $client->getBlockTransactions(5));
        $this->assertSame([Chain::txHash(7, 0), Chain::txHash(7, 1)], $client->getBlockTransactions(7));
        $this->assertStringContainsString('"method":"get_block","params":{"height":4}', FakeNodeServer::requests()[0]['raw']);
    }






    // -----------------------------------------------------------
    // testFeesComeFromGetTransactions
    // -----------------------------------------------------------
    //
    // getTransactionFees POSTs the hashes to /get_transactions,
    // asking for them decoded as JSON, and returns each fee by
    // hash: what a transaction from before RingCT brings in
    // beyond what it pays out.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFeesComeFromGetTransactions(): void
    {
        FakeNodeServer::configure(['height' => 10, 'fees' => [4 => 250000000000, 7 => [100000000000, 20000000000]]]);
        $hashes = [Chain::txHash(4, 0), Chain::txHash(7, 0), Chain::txHash(7, 1)];

        $fees = $this->client()->getTransactionFees($hashes);

        $this->assertSame(array_combine($hashes, [250000000000, 100000000000, 20000000000]), $fees);
        $request = FakeNodeServer::requests()[0];
        $this->assertSame(['POST', '/get_transactions', 'application/json'], [$request['http_method'], $request['path'], $request['content_type']]);
        $this->assertSame(['txs_hashes' => $hashes, 'decode_as_json' => true], $request['body']);
    }






    // -----------------------------------------------------------
    // testRingCtFeeIsRead
    // -----------------------------------------------------------
    //
    // A RingCT transaction hides its amounts; its fee is the
    // one rct_signatures states.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRingCtFeeIsRead(): void
    {
        FakeNodeServer::configure(['height' => 10, 'fees' => [3 => 50000000000], 'rct' => true]);

        $this->assertSame([Chain::txHash(3, 0) => 50000000000], $this->client()->getTransactionFees([Chain::txHash(3, 0)]));
    }






    // -----------------------------------------------------------
    // testRestrictedNodeIsAskedForAHundredTransactionsAtATime
    // -----------------------------------------------------------
    //
    // A restricted node refuses more than 100 transactions a
    // call: 150 go out as 100 and 50, and every fee comes
    // back.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRestrictedNodeIsAskedForAHundredTransactionsAtATime(): void
    {
        FakeNodeServer::configure(['height' => 200, 'range_cap' => 1000, 'fees' => array_fill_keys(range(1, 150), 1000000000)]);
        $hashes = array_map(function (int $height) {
            return Chain::txHash($height, 0);
        }, range(1, 150));

        $fees = $this->client()->getTransactionFees($hashes);

        $this->assertCount(150, $fees);
        $this->assertSame([100, 50], array_map(function (array $request) {
            return count($request['body']['txs_hashes']);
        }, FakeNodeServer::requests()));
    }






    // -----------------------------------------------------------
    // testUnknownTransactionIsRefused
    // -----------------------------------------------------------
    //
    // A transaction the node does not know — in missed_tx — is
    // a DaemonException naming it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testUnknownTransactionIsRefused(): void
    {
        $message = $this->failure($this->client(), function (DaemonClient $client) {
            $client->getTransactionFees([str_repeat('ab', 32)]);
        });

        $this->assertSame('/get_transactions: the node does not know ' . str_repeat('ab', 32), $message);
    }






    // -----------------------------------------------------------
    // client
    // -----------------------------------------------------------
    //
    // A DaemonClient for the stand-in node, or another address,
    // with or without a login.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function client(?string $login = null, string $url = FakeNodeServer::URL): DaemonClient
    {
        return new DaemonClient($url, $login);
    }






    // -----------------------------------------------------------
    // failure
    // -----------------------------------------------------------
    //
    // The message of the DaemonException the call — getInfo
    // unless the test passes another — fails with.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function failure(DaemonClient $client, ?callable $call = null): string
    {
        try {
            $call ? $call($client) : $client->getInfo();
        } catch (DaemonException $failure) {
            return $failure->getMessage();
        }
        $this->fail('the call did not fail');
    }
}
