#A tool to run xcodebuild with specifying developer identity provisioning profile

Script written in PHP. It gives you a list of developer identities to select from and automatically finds a *.mobileprovision file for your selection. Or you can enter your credentials in config and script will silently build your app.

Feel free to send me your propositions and improvements.

###How to use

1. First copy xcodebuildprovisioning.php file into your projects directory (near your *.xcodeproj file). You can enter your identity and provisioning profile filepath, but it is not necessary because the script will prompt you otherwise.

1. Make it executable: chmod +x xcodebuildprovisioning.php

1. That's all, run ./xcodebuildprovisioning.php from your project directory.
