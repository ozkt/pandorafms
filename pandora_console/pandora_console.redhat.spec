#
# Pandora FMS Console
#
%define name        pandorafms_console
%define version     7.0NG.763
%define release     220805

# User and Group under which Apache is running
%define httpd_name  httpd
%define httpd_user  apache
%define httpd_group apache

Summary:            Pandora FMS Console
Name:               %{name}
Version:            %{version}
Release:            %{release}
License:            GPL
Vendor:             Artica ST <info@artica.es>
#Source0:            %{name}-%{version}-%{revision}.tar.gz
Source0:            %{name}-%{version}.tar.gz
URL:                http://www.pandorafms.com
Group:              Productivity/Networking/Web/Utilities
Packager:           Sancho Lerena <slerena@artica.es>
Prefix:              /var/www/html
BuildRoot:          %{_tmppath}/%{name}
BuildArch:          noarch
AutoReq:            0
Requires:           %{httpd_name} >= 2.0.0
Requires:           mod_php >= 7.0
Requires:           php-gd, php-ldap, php-snmp, php-session, php-gettext
Requires:           php-mysqlnd, php-mbstring, php-zip, php-zlib, php-curl
Requires:           xorg-x11-fonts-75dpi, xorg-x11-fonts-misc, php-pecl-zip
Requires:           graphviz
Requires:           openldap-clients libzstd
Provides:           %{name}-%{version}


%description
The Web Console is a web application that allows to see graphical reports, state of every agent, also to access to the information sent by the agent, to see every monitored parameter and to see its evolution throughout the time, to form the different nodes, groups and users of the system. It is the part that interacts with the final user, and that will allows you to administer the system.

%prep
rm -rf $RPM_BUILD_ROOT

%setup -q -n pandora_console

%build

%install
rm -rf $RPM_BUILD_ROOT
mkdir -p $RPM_BUILD_ROOT%{prefix}/pandora_console
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/logrotate.d/
cp -aRf . $RPM_BUILD_ROOT%{prefix}/pandora_console
rm $RPM_BUILD_ROOT%{prefix}/pandora_console/*.spec
rm $RPM_BUILD_ROOT%{prefix}/pandora_console/pandora_console_install
install -m 0644 pandora_console_logrotate_centos $RPM_BUILD_ROOT%{_sysconfdir}/logrotate.d/pandora_console

%clean
rm -rf $RPM_BUILD_ROOT

%post
# Upgrading.
if [ "$1" -eq "1" ]; then
	echo "Updating the database schema."
	/usr/bin/php %{prefix}/pandora_console/godmode/um_client/updateMR.php 2>/dev/null
fi

# Install pandora_websocket_engine service.
cp -pf %{prefix}/pandora_console/pandora_websocket_engine /etc/init.d/
chmod +x /etc/init.d/pandora_websocket_engine

echo "You can now start the Pandora FMS Websocket service by executing"
echo "   /etc/init.d/pandora_websocket_engine start"

# Has an install already been done, if so we only want to update the files
# push install.php aside so that the console works immediately using existing
# configuration.
#
if [ -f %{prefix}/pandora_console/include/config.php ] ; then
   mv %{prefix}/pandora_console/install.php %{prefix}/pandora_console/install.done
else
   echo "Please, now, point your browser to http://your_IP_address/pandora_console/install.php and follow all the steps described on it."
fi

%preun

# Upgrading
if [ "$1" -eq "1" ]; then
        exit 0
fi

%files
%defattr(0644,%{httpd_user},%{httpd_group},0755)
%docdir %{prefix}/pandora_console/docs
%{prefix}/pandora_console
%config(noreplace) %{_sysconfdir}/logrotate.d/pandora_console
%attr(0644, root, root) %{_sysconfdir}/logrotate.d/pandora_console
