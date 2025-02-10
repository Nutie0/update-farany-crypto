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
        public void SendPasswordResetEmail(string email)
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

            var apiBaseUrl = _configuration["ApiBaseUrl"];
            var resetUrl = $"{apiBaseUrl}/api/auth/reset-tentative?email={Uri.EscapeDataString(email)}";

            var message = new MailMessage
            {
                From = new MailAddress(smtpSettings["From"]),
                Subject = "Reset Your Login Attempts",
                Body = $"You have reached the maximum number of login attempts. Click the link below to reset your login attempts:\n\n{resetUrl}",
                IsBodyHtml = false
            };
            message.To.Add(email);

            smtpClient.Send(message);
        }
    }
}
