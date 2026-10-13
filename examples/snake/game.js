'use strict';

/*
 * Snake: jogo de exemplo do Bitman, seguindo docs/protocolo-do-jogo.md.
 * - Só index.html, style.css e game.js; nada externo, sem rede e sem storage.
 * - Fala com o hub só por postMessage: "ready" ao carregar e um único "game-over" com o score.
 * - Uma partida por carregamento: para jogar de novo, use "Jogar de novo" no Bitman (nova sessão).
 */
(function () {
  var GRID = 20;
  var TICK_MS = 110;
  var POINTS_PER_FOOD = 10;

  var canvas = document.getElementById('board');
  var ctx = canvas.getContext('2d');
  var cell = canvas.width / GRID;
  var scoreEl = document.getElementById('score');
  var statusEl = document.getElementById('status');

  var snake = [{ x: 10, y: 10 }, { x: 9, y: 10 }, { x: 8, y: 10 }];
  var direction = { x: 1, y: 0 };
  var nextDirection = direction;
  var food = null;
  var score = 0;
  var state = 'ready'; // ready → playing → over
  var timer = null;

  function sendToHub(type, payload) {
    var message = { source: 'bitman', version: 1, type: type };
    for (var key in payload) {
      message[key] = payload[key];
    }
    // A origem do hub não é conhecida pelo jogo (o iframe tem origem opaca): o hub confere a fonte.
    window.parent.postMessage(message, '*');
  }

  function randomCell() {
    return Math.floor(Math.random() * GRID);
  }

  function occupied(x, y) {
    return snake.some(function (part) {
      return part.x === x && part.y === y;
    });
  }

  function placeFood() {
    var x;
    var y;
    do {
      x = randomCell();
      y = randomCell();
    } while (occupied(x, y));
    food = { x: x, y: y };
  }

  function start() {
    if (state !== 'ready') return;
    state = 'playing';
    statusEl.textContent = 'Setas ou WASD para virar; no celular, deslize.';
    timer = setInterval(step, TICK_MS);
  }

  function finish() {
    clearInterval(timer);
    state = 'over';
    statusEl.textContent = 'Fim de jogo! Para outra partida, use "Jogar de novo" no Bitman.';
    sendToHub('game-over', { score: Math.floor(score) });
    draw();
  }

  function step() {
    direction = nextDirection;
    var head = { x: snake[0].x + direction.x, y: snake[0].y + direction.y };
    var eats = food !== null && head.x === food.x && head.y === food.y;
    // A cauda sai do lugar neste passo, a menos que a cobra coma.
    var body = eats ? snake : snake.slice(0, -1);
    var hitsWall = head.x < 0 || head.y < 0 || head.x >= GRID || head.y >= GRID;
    var hitsSelf = body.some(function (part) {
      return part.x === head.x && part.y === head.y;
    });

    if (hitsWall || hitsSelf) {
      finish();
      return;
    }

    snake = [head].concat(body);
    if (eats) {
      score += POINTS_PER_FOOD;
      scoreEl.textContent = 'Score: ' + score;
      if (snake.length === GRID * GRID) {
        finish();
        return;
      }
      placeFood();
    }
    draw();
  }

  function turn(x, y) {
    if (state === 'ready') start();
    if (state !== 'playing') return;
    // Não dá meia-volta sobre o próprio corpo.
    if (x === -direction.x && y === -direction.y) return;
    nextDirection = { x: x, y: y };
  }

  function drawCell(x, y, color, inset) {
    ctx.fillStyle = color;
    ctx.fillRect(x * cell + inset, y * cell + inset, cell - inset * 2, cell - inset * 2);
  }

  function draw() {
    ctx.fillStyle = '#0f172a';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    if (food !== null) drawCell(food.x, food.y, '#f87171', 3);
    snake.forEach(function (part, index) {
      drawCell(part.x, part.y, index === 0 ? '#6ee7b7' : '#10b981', 1);
    });

    if (state !== 'playing') {
      ctx.fillStyle = 'rgba(2, 6, 23, 0.7)';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#e2e8f0';
      ctx.textAlign = 'center';
      ctx.font = 'bold 28px system-ui, sans-serif';
      ctx.fillText(state === 'ready' ? 'Snake' : 'Fim de jogo', canvas.width / 2, canvas.height / 2 - 10);
      ctx.font = '16px system-ui, sans-serif';
      ctx.fillText(state === 'ready' ? 'Espaço ou toque para começar' : 'Score: ' + score, canvas.width / 2, canvas.height / 2 + 20);
    }
  }

  var keys = {
    ArrowUp: [0, -1], w: [0, -1], W: [0, -1],
    ArrowDown: [0, 1], s: [0, 1], S: [0, 1],
    ArrowLeft: [-1, 0], a: [-1, 0], A: [-1, 0],
    ArrowRight: [1, 0], d: [1, 0], D: [1, 0],
  };

  document.addEventListener('keydown', function (event) {
    if (event.key === ' ' || event.key === 'Enter') {
      event.preventDefault();
      start();
      return;
    }
    var move = keys[event.key];
    if (move) {
      event.preventDefault();
      turn(move[0], move[1]);
    }
  });

  canvas.addEventListener('click', function () {
    canvas.focus();
    start();
  });

  var touchStart = null;
  canvas.addEventListener('touchstart', function (event) {
    touchStart = { x: event.touches[0].clientX, y: event.touches[0].clientY };
  }, { passive: true });
  canvas.addEventListener('touchend', function (event) {
    if (touchStart === null) return;
    var dx = event.changedTouches[0].clientX - touchStart.x;
    var dy = event.changedTouches[0].clientY - touchStart.y;
    touchStart = null;
    if (Math.max(Math.abs(dx), Math.abs(dy)) < 20) {
      start();
    } else if (Math.abs(dx) > Math.abs(dy)) {
      turn(dx > 0 ? 1 : -1, 0);
    } else {
      turn(0, dy > 0 ? 1 : -1);
    }
  });

  placeFood();
  draw();
  canvas.focus();
  sendToHub('ready', {});
})();
