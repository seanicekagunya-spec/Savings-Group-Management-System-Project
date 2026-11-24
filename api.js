/**
 * assets/js/api.js
 * ONE helper every HTML page uses to talk to the PHP backend.
 * Every answer has the same shape: { success, message, data, status }
 *
 * Front-end rule (NFR-05, XSS): show anything that came from the server with
 * element.textContent, NEVER element.innerHTML.
 */
// Must match the project folder inside C:\xampp\htdocs\ (this is "sgms-project").
const API_BASE = '/sgms-project/';

async function apiRequest(path, method = 'GET', body = null) {
  const options = {
    method: method,
    credentials: 'same-origin',   // send the session cookie with the request
    headers: {},
  };
  if (body !== null) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(API_BASE + path, options);
  } catch (error) {
    // The browser could not even connect: Apache is stopped, or the address is wrong.
    return { success: false, message: 'Could not connect to the server. Is Apache running in XAMPP?', data: {}, status: 0 };
  }

  try {
    const result = await response.json();   // the backend always answers in JSON
    result.status = response.status;         // 200, 401, 422 ...
    return result;
  } catch (error) {
    // The server answered, but not with JSON: usually a 404 page because
    // API_BASE does not match the folder name, or a PHP crash.
    return {
      success: false,
      message: 'The server answered with HTTP ' + response.status + ' but not JSON. ' +
               'Check that API_BASE (' + API_BASE + ') matches your folder name in htdocs.',
      data: {},
      status: response.status,
    };
  }
}

const apiGet  = (path)       => apiRequest(path, 'GET');
const apiPost = (path, body) => apiRequest(path, 'POST', body);