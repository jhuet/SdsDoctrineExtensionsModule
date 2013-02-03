<?php

namespace Sds\ModuleUnitTester\BaseTest;

use Sds\DoctrineExtensionsModule\Test\TestAsset\Document\Game;
use Sds\ModuleUnitTester\AbstractControllerTest;
use Zend\Http\Header\GenericHeader;
use Zend\Http\Request;

class JsonRestfulControllerEndpointTest extends AbstractControllerTest{

    protected static $staticDcumentManager;

    protected static $dbDataCreated = false;

    public static function tearDownAfterClass(){
        //Cleanup db after all tests have run
        $collections = static::$staticDcumentManager->getConnection()->selectDatabase('doctrineExtensionsModuleTest')->listCollections();
        foreach ($collections as $collection) {
            $collection->remove(array(), array('safe' => true));
        }
    }

    public function setUp(){

        $this->controllerName = 'Sds\DoctrineExtensionsModule\Controller\JsonRestfulController';

        parent::setUp();

        $this->documentManager = $this->serviceManager->get('doctrine.documentmanager.odm_default');
        static::$staticDcumentManager = $this->documentManager;

        if ( ! static::$dbDataCreated){
            //Create data in the db to query against

            $this->documentManager->persist(new Game('dweebies', 'card'));
            $this->documentManager->persist(new Game('monsta', 'card'));
            $this->documentManager->persist(new Game('7wonders', 'board'));
            $this->documentManager->persist(new Game('rallyman', 'race'));
            $this->documentManager->flush();

            static::$dbDataCreated = true;
        }

        $this->routeMatch->setParam('restEndpoint', 'game');
    }

    public function testGet(){

        $this->routeMatch->setParam('id', 'dweebies');
        $this->request->setMethod(Request::METHOD_GET);

        $result = $this->getController()->dispatch($this->request, $this->response);
        $returnArray = $result->getVariables();

        $this->assertEquals('dweebies', $returnArray['name']);
        $this->assertEquals('card', $returnArray['type']);
    }

    public function testGetFail(){

        $this->setExpectedException('Sds\DoctrineExtensionsModule\Exception\DocumentNotFoundException');

        $this->routeMatch->setParam('id', 'monpoly');
        $this->request->setMethod(Request::METHOD_GET);

        $this->getController()->dispatch($this->request, $this->response);
    }

    public function testGetList(){

        $this->request->setMethod(Request::METHOD_GET);

        $result = $this->getController()->dispatch($this->request, $this->response);
        $returnArray = $result->getVariables();

        $this->assertCount(4, $returnArray);
        $this->assertEquals('Content-Range: 0-4/4', $this->response->getHeaders()->get('Content-Range')->toString());
    }

    public function testGetSortedList(){

        $this->request->getQuery()->set('sort(+type,+name)', null);
        $this->request->setMethod(Request::METHOD_GET);

        $result = $this->getController()->dispatch($this->request, $this->response);
        $returnArray = $result->getVariables();

        $this->assertCount(4, $returnArray);
        $this->assertEquals('Content-Range: 0-4/4', $this->response->getHeaders()->get('Content-Range')->toString());
        $this->assertEquals('7wonders', $returnArray[0]['name']);
        $this->assertEquals('dweebies', $returnArray[1]['name']);
        $this->assertEquals('monsta', $returnArray[2]['name']);
        $this->assertEquals('rallyman', $returnArray[3]['name']);
    }

    public function testGetOffsetList(){

        $this->request->getHeaders()->addHeader(GenericHeader::fromString('Range: items=2-100'));
        $this->request->setMethod(Request::METHOD_GET);

        $result = $this->getController()->dispatch($this->request, $this->response);
        $returnArray = $result->getVariables();

        $this->assertCount(2, $returnArray);
        $this->assertEquals('Content-Range: 2-4/4', $this->response->getHeaders()->get('Content-Range')->toString());
    }

    public function testCreate(){

        $this->request->setMethod(Request::METHOD_POST);
        $this->request->getHeaders()->addHeader(GenericHeader::fromString('Content-type: application/json'));
        $this->request->setContent('{"name": "forbiddenIsland", "type": "co-op"}');

        $result = $this->getController()->dispatch($this->request, $this->response);
        $returnArray = $result->getVariables();

        $this->assertEquals('forbiddenIsland', $returnArray['name']);
        $this->assertEquals('co-op', $returnArray['type']);
    }

    public function testCreateFail(){

        $this->setExpectedException('Sds\DoctrineExtensionsModule\Exception\InvalidArgumentException');

        $this->request->setMethod(Request::METHOD_POST);
        $this->request->getHeaders()->addHeader(GenericHeader::fromString('Content-type: application/json'));
        $this->request->setContent('{"name": "missingType"}');

        $this->getController()->dispatch($this->request, $this->response);
    }

    public function testUpdate(){

        $this->routeMatch->setParam('id', 'forbiddenIsland');

        $this->request->setMethod(Request::METHOD_PUT);
        $this->request->getHeaders()->addHeader(GenericHeader::fromString('Content-type: application/json'));
        $this->request->setContent('{"type": "board"}');

        $result = $this->getController()->dispatch($this->request, $this->response);
        $returnArray = $result->getVariables();

        $this->assertEquals('forbiddenIsland', $returnArray['name']);
        $this->assertEquals('board', $returnArray['type']);
    }

    public function testUpdateFail(){

        $this->setExpectedException('Sds\DoctrineExtensionsModule\Exception\InvalidArgumentException');

        $this->routeMatch->setParam('id', 'forbiddenIsland');

        $this->request->setMethod(Request::METHOD_PUT);
        $this->request->getHeaders()->addHeader(GenericHeader::fromString('Content-type: application/json'));
        $this->request->setContent('{"type": null}');

        $this->getController()->dispatch($this->request, $this->response);
    }

    public function testDelete(){

        $this->request->setMethod(Request::METHOD_DELETE);
        $this->routeMatch->setParam('id', 'dweebies');

        $this->getController()->dispatch($this->request, $this->response);

        $game = $this->documentManager
            ->getRepository('Sds\DoctrineExtensionsModule\Test\TestAsset\Document\Game')
            ->findOneBy(['name' => 'dweebies']);

        $this->assertNull($game);
    }
}
