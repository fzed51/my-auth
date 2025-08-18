<?php

class TestLongStrings
{
    public function testMethod(): string
    {
        // Test avec une chaîne très longue qui dépasse 80 caractères
        $longString = "Cette chaîne de caractères est très longue et dépasse largement la limite de 80 caractères par ligne";
        
        // Test avec concaténation
        $concatenated = "Première partie" . " et deuxième partie de la chaîne qui est également très longue";
        
        // Test avec plusieur lignes
        $multilines = '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Welcome to Test!</title>
            </head>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h1>Welcome to MyAuth, %s!</h1>
                
                <p>Your email has been successfully verified and your account
                    is now active.</p>
                
                <p>You can now:</p>
                <ul>
                    <li>Sign in to your account</li>
                    <li>Update your profile</li>
                    <li>Access all our features</li>
                </ul>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="%s/login" style="background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
                        Sign In Now
                    </a>
                </div>
                
                <p>If you have any questions, feel free to contact our support team.</p>
                
                <p>Welcome aboard!</p>
                <p>The MyAuth Team</p>
            </body>
            </html>';

        // Test avec paramètres et chaînes longues
        $result = sprintf("Template avec paramètres %s et %s qui crée une ligne très longue", $param1, $param2);
        
        return $result;
    }
}
