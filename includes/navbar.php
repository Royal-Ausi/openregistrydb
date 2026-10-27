<?php
/**
 * Shared top navigation bar — included by admin and user dashboards.
 * Font colour: #f5f5f5  |  Cursor: pointer on interactive elements
 * No hover colour change on navbar links (per design spec).
 */
?>
<style>
  /*  Shared Navbar  */
  .or-navbar {
    background-color: #E08D3C;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 56px;
    z-index: 1100;
    padding: 0 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,.18);
  }
  .or-navbar .nav-left  { display:flex; align-items:center; gap:14px; }
  .or-navbar .nav-right { display:flex; align-items:center; gap:10px; }

  /* Hamburger */
  .or-navbar .menu-toggle {
    font-size: 22px;
    cursor: pointer;
    color: #f5f5f5;
    background: none;
    border: none;
    padding: 4px 8px;
    line-height: 1;
    user-select: none;
  }
  /* All text in the navbar: #f5f5f5, no hover colour change */
  .or-navbar,
  .or-navbar a,
  .or-navbar span,
  .or-navbar button,
  .or-navbar .navbar-brand,
  .or-navbar .navbar-brand:hover {
    color: #f5f5f5;
    text-decoration: none;
  }
  /* pointer on anything interactive, but NO background/colour change on hover */
  .or-navbar a,
  .or-navbar button,
  .or-navbar .menu-toggle {
    cursor: pointer;
  }
  .or-navbar a:hover,
  .or-navbar button:hover,
  .or-navbar .menu-toggle:hover {
    color: #f5f5f5 !important;
    background: transparent !important;
    text-decoration: none;
  }

  .or-navbar .navbar-logo {
    max-height: 36px;
    width: auto;
    border-radius: 7px;
    box-shadow: 0 2px 4px rgba(0,0,0,.22);
  }
  .or-navbar .navbar-brand {
    font-weight: 700;
    font-size: 15px;
    margin: 0;
  }
  .or-navbar .btn-back {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.4);
    color: #f5f5f5 !important;
    font-size: 13px;
    padding: 5px 14px;
    border-radius: 6px;
    cursor: pointer;
  }
  .or-navbar .btn-back:hover {
    background: rgba(255,255,255,.15) !important;
    border-color: rgba(255,255,255,.4) !important;
  }
</style>

<nav class="or-navbar" id="orNavbar">
  <div class="nav-left">
    <button class="menu-toggle" onclick="toggleSidebar()" title="Toggle sidebar" aria-label="Toggle sidebar">☰</button>
    <img src="includes/images/mlhud jpeg 6.jpg" alt="MLHUD Logo" class="navbar-logo">
    <span class="navbar-brand">Open Registry | MLHUD</span>
  </div>
  <div class="nav-right">
    <button class="btn-back" onclick="history.back()">← Back</button>
  </div>
</nav>
