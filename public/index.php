<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use App\Controller\SongController;
use App\Controller\ArtistController;
use App\Repository\SongRepository;
use App\Repository\ArtistRepository;
use App\ChordPro\Parser;
use App\ChordPro\Renderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . "/../.env");

$dsn = sprintf(
    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
    $_ENV["DB_HOST"],
    $_ENV["DB_PORT"],
    $_ENV["DB_NAME"]
);

$pdo = new PDO(
    $dsn, $_ENV["DB_USER"],
    $_ENV["DB_PASSWORD"],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$parser = new Parser();
$renderer = new Renderer();

// Repository-слой
$songRepo = new SongRepository($pdo);
$artistRepo = new ArtistRepository($pdo);

// Контроллеры
$songController = new SongController($songRepo, $artistRepo, $twig, $parser, $renderer);
$artistController = new ArtistController($artistRepo, $twig);

// Маршруты — без изменений
$routes = new RouteCollection();
$routes->add('songs_list', new Route('/', ['_controller' => 'SongController', '_method' => 'list']));
$routes->add('songs_create', new Route('/songs', ['_controller' => 'SongController', '_method' => 'create']));
$routes->add('songs_new', new Route('/songs/new', ['_controller' => 'SongController', '_method' => 'new']));
$routes->add('songs_edit', new Route('/songs/{slug}/edit', ['_controller' => 'SongController', '_method' => 'edit']));
$routes->add('songs_update', new Route('/songs/{slug}/update', ['_controller' => 'SongController', '_method' => 'update']));
$routes->add('songs_delete', new Route('/songs/{slug}/delete', ['_controller' => 'SongController', '_method' => 'delete']));
$routes->add('songs_show', new Route('/songs/{slug}', ['_controller' => 'SongController', '_method' => 'show']));
$routes->add('artists_list', new Route('/artists', ['_controller' => 'ArtistController', '_method' => 'list'], [], [], '', [], ['GET']));
$routes->add('artists_new', new Route('/artists/new', ['_controller' => 'ArtistController', '_method' => 'new']));
$routes->add('artists_create', new Route('/artists', ['_controller' => 'ArtistController', '_method' => 'create'], [], [], '', [], ['POST']));
$routes->add('artists_edit', new Route('/artists/{slug}/edit', ['_controller' => 'ArtistController', '_method' => 'edit']));
$routes->add('artists_update', new Route('/artists/{slug}/update', ['_controller' => 'ArtistController', '_method' => 'update']));
$routes->add('artists_delete', new Route('/artists/{slug}/delete', ['_controller' => 'ArtistController', '_method' => 'delete']));
$routes->add('artists_show', new Route('/artists/{slug}', ['_controller' => 'ArtistController', '_method' => 'show']));

// Запрос — без изменений
$request = Request::createFromGlobals();
$context = new RequestContext();
$context->fromRequest($request);
$matcher = new UrlMatcher($routes, $context);

try {
    $parameters = $matcher->match($request->getPathInfo());
    $controllerName = $parameters['_controller'];
    $method = $parameters['_method'];
    unset($parameters['_controller'], $parameters['_method'], $parameters['_route']);

    $controller = match ($controllerName) {
        'SongController' => $songController,
        'ArtistController' => $artistController,
    };

    $response = $controller->$method($request, ...$parameters);
} catch (ResourceNotFoundException $e) {
    $response = new Response('Страница не найдена', Response::HTTP_NOT_FOUND);
}

$response->send();