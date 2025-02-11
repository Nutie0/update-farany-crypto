using Microsoft.EntityFrameworkCore;
using Microsoft.OpenApi.Models;
using Microsoft.AspNetCore.Identity;
using Microsoft.IdentityModel.Tokens;
using Microsoft.Extensions.Options;
using UserApi.Models;
using UserApi.Services;
using Microsoft.AspNetCore.Authentication.JwtBearer;
using Microsoft.Extensions.Caching.Memory;  // Ajout pour IMemoryCache
using System.Text;

var builder = WebApplication.CreateBuilder(args);

// Ajouter cette ligne pour écouter sur toutes les interfaces
builder.WebHost.ConfigureKestrel(options =>
{
    options.ListenAnyIP(5000); // Écoute sur le port 5000 pour toutes les interfaces
});

// Configuration de la base de données
builder.Services.AddDbContext<UserApi.Data.ApplicationDbContext>(options =>
    options.UseNpgsql(builder.Configuration.GetConnectionString("DefaultConnection")));

// Configuration des services
builder.Services.AddControllers();
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();
builder.Services.AddMemoryCache();

// Configuration CORS
builder.Services.AddCors(options =>
{
    options.AddPolicy("AllowAll", builder =>
    {
        builder.AllowAnyOrigin()
               .AllowAnyMethod()
               .AllowAnyHeader();
    });
});

// Services personnalisés
builder.Services.AddScoped<PasswordHasher>();
builder.Services.AddScoped<EmailService>();

// Enregistrement du service PasswordHasher
builder.Services.AddScoped<IPasswordHasher<Utilisateur>, PasswordHasher<Utilisateur>>();

// Configuration du JWT
var jwtSettings = builder.Configuration.GetSection("JwtSettings").Get<JwtSettings>();

builder.Services.AddAuthentication(JwtBearerDefaults.AuthenticationScheme)
    .AddJwtBearer(options =>
    {
        options.RequireHttpsMetadata = false;
        options.SaveToken = true;
        options.TokenValidationParameters = new TokenValidationParameters
        {
            ValidateIssuer = true,
            ValidateAudience = true,
            ValidateLifetime = true,  // Validation de la durée de vie du token
            ValidIssuer = jwtSettings.Issuer,
            ValidAudience = jwtSettings.Audience,
            IssuerSigningKey = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(jwtSettings.SecretKey)),
            ClockSkew = TimeSpan.Zero  // Pas de délai toléré pour l'expiration du token
        };
    });

var app = builder.Build();

// Configuration du port 5280
app.Urls.Add("http://localhost:5280");

// Utiliser Swagger pour la documentation de l'API en mode développement
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI(c => c.SwaggerEndpoint("/swagger/v1/swagger.json", "My API v1"));
}

// Middleware
app.UseCors("AllowAll");
app.UseAuthentication();  // Permet l'authentification avec le JWT
app.UseAuthorization();   // Permet d'utiliser les autorisations définies

// Middleware pour la redirection HTTPS
app.UseHttpsRedirection();

// Mapper les contrôleurs
app.MapControllers();

app.Run();
