/*
 * The start of the React app: it loads the stylesheet and puts <App /> on the page.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { App } from './App.jsx';
import { SessionProvider } from './session.jsx';
import './styles/app.css';

createRoot(document.getElementById('root')).render(
    <StrictMode>
        <BrowserRouter>
            <SessionProvider>
                <App />
            </SessionProvider>
        </BrowserRouter>
    </StrictMode>,
);
