/*
 * The frame around every page: skip link, header with navigation, the flash
 * message, the page itself (<Outlet />) and the footer.
 */
import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import { useSession } from '../session.jsx';
import { LogoMark } from './ui.jsx';

const DISCOVER_LINKS = [['/explore', 'Explore'], ['/events', 'Events'], ['/organizations', 'Organizations'], ['/resources', 'Resources']];

export function Layout() {
    const { user, flash, notify, applySession } = useSession();
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    const navigate = useNavigate();

    // Close the small-screen menu after moving to another page, and start at the top.
    useEffect(() => {
        setMenuOpen(false);
        window.scrollTo(0, 0);
    }, [location.pathname]);

    // Close the menu with the Escape key.
    useEffect(() => {
        const onKey = (event) => event.key === 'Escape' && setMenuOpen(false);
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    const personalLinks = [['/dashboard', 'Dashboard'], ['/saved', 'Saved'], ['/calendar', 'Calendar']];
    if (user?.role === 'org_rep') {
        personalLinks.push(['/manage', 'Manage']);
    }
    if (user?.role === 'admin') {
        personalLinks.push(['/admin', 'Admin']);
    }

    async function signOut() {
        try {
            const answer = await api.post('/api/logout');
            applySession(answer);
            notify(answer.message, 'info', true);
            navigate('/');
        } catch (error) {
            notify(error.message, 'error');
        }
    }

    return (
        <>
            <a className="skip-link" href="#main">Skip to main content</a>

            <header className="site-header">
                <div className="container header-row">
                    <Link className="logo" to="/" aria-label="CampusBridge home">
                        <LogoMark />
                        <span>Campus<strong>Bridge</strong></span>
                    </Link>

                    <button className="menu-button" type="button" aria-expanded={menuOpen} aria-controls="site-menu" onClick={() => setMenuOpen(!menuOpen)}>
                        <span className="sr-only">Menu</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>

                    <div className={`site-menu${menuOpen ? ' is-open' : ''}`} id="site-menu">
                        <nav aria-label="Main">
                            <ul className="nav-list">
                                {DISCOVER_LINKS.map(([path, label]) => <li key={path}><NavLink to={path}>{label}</NavLink></li>)}
                                <li className="nav-divider" aria-hidden="true"></li>
                                {personalLinks.map(([path, label]) => <li key={path}><NavLink to={path}>{label}</NavLink></li>)}
                            </ul>
                        </nav>

                        <div className="account-area">
                            {user ? (
                                <>
                                    <Link className="account-name" to="/preferences" title="Preferences">
                                        {user.name}
                                        <span className="role-tag">{user.role_label}</span>
                                    </Link>
                                    <button className="button button-ghost button-small" type="button" onClick={signOut}>Sign out</button>
                                </>
                            ) : (
                                <>
                                    <Link className="button button-ghost button-small" to="/login">Sign in</Link>
                                    <Link className="button button-primary button-small" to="/register">Create account</Link>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </header>

            <main id="main" tabIndex={-1}>
                {flash && (
                    <div className="container">
                        <p className={`flash flash-${flash.type}`} role="status">{flash.message}</p>
                    </div>
                )}
                <Outlet />
            </main>

            <footer className="site-footer">
                <div className="container footer-grid">
                    <div>
                        <p className="footer-brand"><LogoMark /> CampusBridge</p>
                        <p className="muted small">
                            An independent student project (CS341 Web Technologies). CampusBridge is not affiliated with
                            or endorsed by any university. All organizations, opportunities and events shown are{' '}
                            <strong>fictional sample data</strong>.
                        </p>
                    </div>
                    <nav aria-label="Footer: discover">
                        <p className="footer-heading">Discover</p>
                        <ul>
                            <li><Link to="/explore?type=opportunity">Opportunities</Link></li>
                            <li><Link to="/events">Events</Link></li>
                            <li><Link to="/organizations">Organizations</Link></li>
                        </ul>
                    </nav>
                    <nav aria-label="Footer: about">
                        <p className="footer-heading">About</p>
                        <ul>
                            <li><Link to="/about">About CampusBridge</Link></li>
                            <li><Link to="/about#recommendations">How recommendations work</Link></li>
                            <li><Link to="/about#privacy">Privacy &amp; data</Link></li>
                        </ul>
                    </nav>
                </div>
            </footer>
        </>
    );
}
