/*
 * The frame around every page: skip link, the top bar (logo + search, icon
 * tabs, account menu), the flash message, the page itself (<Outlet />) and
 * the footer.
 */
import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import { useSession } from '../session.jsx';
import { Icon, InitialsAvatar } from './icons.jsx';
import { LogoMark } from './ui.jsx';

/** The icon tabs in the middle of the top bar. */
export const MAIN_TABS = [
    ['/', 'Home', 'home'],
    ['/explore', 'Explore', 'explore'],
    ['/events', 'Events', 'events'],
    ['/organizations', 'Organizations', 'organizations'],
    ['/resources', 'Resources', 'resources'],
    ['/rooms', 'Focus rooms', 'focus'],
];

/** Links for the signed-in user (account menu and the home sidebar). */
export function personalLinks(user) {
    if (!user) {
        return [];
    }
    const links = [
        ['/dashboard', 'Dashboard', 'dashboard'],
        ['/saved', 'Saved', 'saved'],
        ['/calendar', 'Calendar', 'calendar'],
    ];
    if (user.role === 'org_rep') {
        links.push(['/manage', 'Manage', 'manage']);
    }
    if (user.role === 'admin') {
        links.push(['/admin', 'Admin', 'admin']);
    }
    if (user.role === 'campus_rep' || user.role === 'admin') {
        links.push(['/approvals', 'Approvals', 'approvals']);
    }
    links.push(['/preferences', 'Preferences', 'preferences']);
    return links;
}

export function Layout() {
    const { user, flash, notify, applySession } = useSession();
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);
    const location = useLocation();
    const navigate = useNavigate();
    const isHome = location.pathname === '/';

    // Close the account menu after moving to another page, and start at the top.
    useEffect(() => {
        setMenuOpen(false);
        window.scrollTo(0, 0);
    }, [location.pathname]);

    // Close the account menu with Escape or a click outside it.
    useEffect(() => {
        const onKey = (event) => event.key === 'Escape' && setMenuOpen(false);
        const onClick = (event) => menuRef.current && !menuRef.current.contains(event.target) && setMenuOpen(false);
        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onClick);
        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onClick);
        };
    }, []);

    function search(event) {
        event.preventDefault();
        const q = new FormData(event.target).get('q').trim();
        navigate(q ? `/explore?q=${encodeURIComponent(q)}` : '/explore');
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
                <div className="topbar">
                    <div className="topbar-start">
                        <Link className="logo" to="/" aria-label="CampusBridge home">
                            <LogoMark />
                            <span className="logo-text">Campus<strong>Bridge</strong></span>
                        </Link>
                        <form className="topbar-search" role="search" onSubmit={search}>
                            <label className="sr-only" htmlFor="top-search">Search CampusBridge</label>
                            <Icon name="search" />
                            <input id="top-search" type="search" name="q" maxLength={100} placeholder="Search CampusBridge" />
                        </form>
                    </div>

                    <nav className="topbar-tabs" aria-label="Main">
                        {MAIN_TABS.map(([path, label, icon]) => (
                            <NavLink key={path} to={path} end={path === '/'} className="icon-tab" title={label}>
                                <Icon name={icon} />
                                <span className="icon-tab-label">{label}</span>
                            </NavLink>
                        ))}
                    </nav>

                    <div className="topbar-end">
                        {user ? (
                            <div className="account-menu" ref={menuRef}>
                                <button className="avatar-button" type="button" aria-expanded={menuOpen} aria-controls="account-panel" onClick={() => setMenuOpen(!menuOpen)}>
                                    <InitialsAvatar name={user.name} size="small" src={user.avatar_url} />
                                    <span className="sr-only">Account menu for {user.name}</span>
                                </button>
                                <div className="account-panel card" id="account-panel" hidden={!menuOpen}>
                                    <Link className="account-panel-head" to="/profile">
                                        <InitialsAvatar name={user.name} src={user.avatar_url} />
                                        <span>
                                            <strong>{user.name}</strong>
                                            <span className="muted small">{user.role_label} · View your profile</span>
                                        </span>
                                    </Link>
                                    <ul className="menu-list">
                                        {personalLinks(user).map(([path, label, icon]) => (
                                            <li key={path}><NavLink to={path}><span className="menu-icon"><Icon name={icon} /></span>{label}</NavLink></li>
                                        ))}
                                        <li>
                                            <button type="button" onClick={signOut}><span className="menu-icon"><Icon name="signout" /></span>Sign out</button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        ) : (
                            <>
                                <Link className="button button-ghost button-small" to="/login">Sign in</Link>
                                <Link className="button button-primary button-small" to="/register">Create account</Link>
                            </>
                        )}
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

            {/* The home page shows these links in its right-hand column instead. */}
            {!isHome && (
                <footer className="site-footer">
                    <div className="container footer-row">
                        <FooterText />
                    </div>
                </footer>
            )}
        </>
    );
}

/** The disclaimer and about links (footer, and the home page's side column). */
export function FooterText() {
    return (
        <>
            <p>
                CampusBridge · an independent CS341 student project, not affiliated with any university.
                All content is fictional sample data.
            </p>
            <nav aria-label="About">
                <ul className="footer-links">
                    <li><Link to="/about">About</Link></li>
                    <li><Link to="/about#recommendations">How recommendations work</Link></li>
                    <li><Link to="/about#privacy">Privacy</Link></li>
                </ul>
            </nav>
        </>
    );
}
