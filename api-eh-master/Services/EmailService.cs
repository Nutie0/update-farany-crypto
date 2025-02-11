using System;
using System.Net;
using System.Net.Mail;
using Microsoft.Extensions.Configuration;

namespace UserApi.Services
{
    public class EmailService
    {
        private readonly IConfiguration _configuration;

        public EmailService(IConfiguration configuration)
        {
            _configuration = configuration;
        }

        // Méthode pour envoyer un email avec le PIN généré
        public void SendPinEmail(string email, string pin)
        {
            var smtpSettings = _configuration.GetSection("SmtpSettings");

            var smtpClient = new SmtpClient
            {
                Host = smtpSettings["Host"],
                Port = int.Parse(smtpSettings["Port"]),
                EnableSsl = true,
                Credentials = new NetworkCredential(
                    smtpSettings["Username"],
                    smtpSettings["Password"]
                )
            };

            var message = new MailMessage
            {
                From = new MailAddress(smtpSettings["From"]),
                Subject = "Your Login PIN",
                Body = $"Your login PIN is: {pin}",
                IsBodyHtml = false
            };
            message.To.Add(email);

            smtpClient.Send(message);
        }

        // Méthode pour générer un code PIN aléatoire
        public string GenerateRandomPin()
        {
            Random random = new Random();
            return random.Next(100000, 999999).ToString(); // Code PIN à 6 chiffres
        }

        // Méthode pour envoyer un email de réinitialisation des tentatives de connexion
        public void SendVerificationEmail(string email, string token)
        {
            var smtpSettings = _configuration.GetSection("SmtpSettings");
            var apiUrl = _configuration["AppSettings:BaseUrl"] ?? "http://localhost:8000"; // Utiliser la configuration

            var smtpClient = new SmtpClient
            {
                Host = smtpSettings["Host"],
                Port = int.Parse(smtpSettings["Port"]),
                EnableSsl = true,
                Credentials = new NetworkCredential(
                    smtpSettings["Username"],
                    smtpSettings["Password"]
                )
            };

            var verificationLink = $"{apiUrl}/api/auth/verify-email?token={token}";

            var message = new MailMessage
            {
                From = new MailAddress(smtpSettings["From"]),
                Subject = "Vérification de votre adresse email",
                Body = $@"
                <h2>Bienvenue sur notre plateforme !</h2>
                <p>Cliquez sur le lien ci-dessous pour vérifier votre adresse email :</p>
                <p><a href='{verificationLink}'>{verificationLink}</a></p>
                <p>Ce lien expirera dans 24 heures.</p>",
                IsBodyHtml = true
            };
            message.To.Add(email);

            smtpClient.Send(message);
        }

        public string GenerateVerificationToken()
        {
            return Convert.ToBase64String(Guid.NewGuid().ToByteArray());
        }
    }
}
