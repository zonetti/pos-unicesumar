<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

$loader = require_once __DIR__.'/vendor/autoload.php';

$db = new PDO('sqlite:beers.db');
$app = new Application();
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/views',
));

$estilos = array('Pilsen' , 'Stout');

$app->get('/estilo', function () use ($estilos, $app) {
    return new Response(implode(',', $estilos), 200);
});

$app->get('/cerveja/{id}', function ($id) use ($app, $db) {
    if ($id == null) {
        $stmt = $db->prepare('select * from beer');
        $stmt->execute();
        $cervejas = $stmt->fetchAll(PDO:FETCH_ASSOC);
        return new Response(json_encode($cervejas));
    }
    $stmt = $db->prepare('select * from beer where id=:id');
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $cervejas = $stmt->fetchAll(PDO:FETCH_ASSOC);
    if (count($cervejas) === 0) {
        return new Response('Não encontrado', 404);
    }
    return new Response(json_encode($cervejas[0]));
})->value('id', null);

$app->post('/cerveja', function (Request $request) use ($app, $db) {
    $db->exec(
        'create table if not exists beer (id INTEGER PRIMARY KEY AUTOINCREMENT, name text not null, style text not null)'
    );
    if (!$request->get('name') || !$request->get('style')) {
        return new Response('Faltam parâmetros', 400);
    }
    $cerveja = [
        'name'  => $request->get('name'),
        'style' => $request->get('style')
    ];
    $stmt = $db->prepare('insert into beer (name, style) values (:name, :style)');
    $stmt->bindParam(':name', $cerveja['name']);
    $stmt->bindParam(':style', $cerveja['style']);
    $stmt->execute();
    $cerveja['id'] = $db->lastInsertId();
    return new Response($cerveja['id']);
});

$app->put('/cerveja/{id}', function (Request $request, $id) use ($app) {
    if ($id == null) {
        return new Response('Não encontrado', 404);
    }
    if (!$request->get('name') && !$request->get('style')) {
        return new Response('Faltam parâmetros', 400);
    }
    $stmt = $db->prepare('update beer set name=:name, style=:style where id=:id');
    if ($request->get('name')) {
        $name = $request->get('name');
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    }
    if ($request->get('style')) {
        $style = $request->get('style');
        $stmt->bindParam(':style', $style, PDO::PARAM_STR);
    }
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return Response('Cerveja atualizada com sucesso');
});

$app->delete('/cerveja/{id}', function (Request $request, $id) use ($app) {
    if ($id == null) {
        return new Response('Não encontrado', 404);
    }
    $stmt = $db->prepare('delete from beer where id=:id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return Response('Cerveja excluída com sucesso');
});

$app->before(function (Request $request) use ($app) {
    if (!$request->headers->has('authorization')) {
        return new Response('Unauthorized', 401);
    }
    $clients = require_once 'config/clients.php';
    if (!in_array($request->headers->get('authorization'), array_keys($clients))) {
        return new Response('Unauthorized', 401);
    }
});

$app->after(function (Request $request, Response $response) use ($app) {
    $content = explode(',', $response->getContent());
    if ($request->headers->get('accept') == 'text/json') {
        $response->headers->set('Content-Type', 'text/json');
        $response->setContent(json_encode($content));
    }
    if ($request->headers->get('accept') == 'text/html') {
        $content = $app['twig']->render('content.twig', array('content' => $content));
        $response->setContent($content);
    }
    return $response;
});

$app->run();
