ALTER TABLE server_access
    MODIFY COLUMN server_path VARCHAR(255) NOT NULL DEFAULT '/CMS',
    MODIFY COLUMN html_path VARCHAR(255) NOT NULL DEFAULT '/Frontend';

UPDATE server_access
SET server_path = '/CMS'
WHERE server_path IN ('', '/cms');

UPDATE server_access
SET html_path = '/Frontend'
WHERE html_path IN ('', '/html');
