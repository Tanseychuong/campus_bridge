<?php
/*
 * If the whole project folder is placed inside AMPPS "www", visiting the folder
 * lands here. Send the visitor to the real website in the public/ folder.
 */
header('Location: public/');
