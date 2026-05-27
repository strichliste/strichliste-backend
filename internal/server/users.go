package server

import (
	"net/http"
	"sort"
	"strconv"
	"strings"
	"time"
	"unicode/utf8"

	"github.com/gin-gonic/gin"
	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/model"
	"github.com/strichliste/strichliste-backend/internal/rules"
	"gorm.io/gorm"
)

func (s *Server) registerUsers(api *gin.RouterGroup) {
	api.GET("/user", s.listUsers)
	api.POST("/user", s.createUser)
	api.GET("/user/search", s.searchUsers)
	api.GET("/user/:userId", s.getUser)
	api.POST("/user/:userId", s.updateUser)
}

// findUserByIdentifier looks up a user by numeric id or, failing that, by name.
func (s *Server) findUserByIdentifier(identifier string) (*model.User, error) {
	var user model.User
	if id, err := strconv.Atoi(identifier); err == nil {
		err := s.db.First(&user, id).Error
		if err == gorm.ErrRecordNotFound {
			return nil, nil
		}
		return &user, err
	}
	err := s.db.Where("name = ?", identifier).First(&user).Error
	if err == gorm.ErrRecordNotFound {
		return nil, nil
	}
	return &user, err
}

func (s *Server) listUsers(c *gin.Context) {
	active := c.Query("active")
	cutoff, _ := rules.StaleDateTime(s.settings, time.Now())

	q := s.db.Where("disabled = ?", false)
	switch active {
	case "true":
		q = q.Where("updated IS NOT NULL AND updated >= ?", cutoff)
	case "false":
		q = q.Where("updated IS NULL OR updated <= ?", cutoff)
	}

	var users []model.User
	if err := q.Find(&users).Error; err != nil {
		fail(c, err)
		return
	}

	sort.SliceStable(users, func(i, j int) bool {
		return natCaseLess(users[i].Name, users[j].Name)
	})

	out := make([]map[string]any, 0, len(users))
	for i := range users {
		out = append(out, s.ser.User(&users[i]))
	}
	c.JSON(http.StatusOK, gin.H{"users": out})
}

func (s *Server) createUser(c *gin.Context) {
	p, err := parseParams(c)
	if err != nil {
		fail(c, apierror.ParameterInvalid("body"))
		return
	}

	name := p.String("name")
	if name == "" {
		fail(c, apierror.ParameterMissing("name"))
		return
	}
	name = sanitizeName(name)
	if name == "" || utf8.RuneCountInString(name) > 64 {
		fail(c, apierror.ParameterInvalid("name"))
		return
	}

	var existing model.User
	if err := s.db.Where("name = ?", name).First(&existing).Error; err == nil {
		fail(c, apierror.UserAlreadyExists(name))
		return
	}

	user := model.User{Name: name}

	if email := p.String("email"); email != "" {
		if !validEmail(email) || utf8.RuneCountInString(email) > 255 {
			fail(c, apierror.ParameterInvalid("email"))
			return
		}
		trimmed := strings.TrimSpace(email)
		user.Email = &trimmed
	}

	if err := s.db.Create(&user).Error; err != nil {
		fail(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"user": s.ser.User(&user)})
}

func (s *Server) searchUsers(c *gin.Context) {
	query := c.Query("query")
	limit := queryInt(c, "limit", 25)

	var users []model.User
	if err := s.db.
		Where("name LIKE ? AND disabled = ?", "%"+query+"%", false).
		Order("name").
		Limit(limit).
		Find(&users).Error; err != nil {
		fail(c, err)
		return
	}

	out := make([]map[string]any, 0, len(users))
	for i := range users {
		out = append(out, s.ser.User(&users[i]))
	}
	c.JSON(http.StatusOK, gin.H{"count": len(users), "users": out})
}

func (s *Server) getUser(c *gin.Context) {
	userID := c.Param("userId")
	user, err := s.findUserByIdentifier(userID)
	if err != nil {
		fail(c, err)
		return
	}
	if user == nil {
		fail(c, apierror.UserNotFound(userID))
		return
	}
	c.JSON(http.StatusOK, gin.H{"user": s.ser.User(user)})
}

func (s *Server) updateUser(c *gin.Context) {
	userID := c.Param("userId")
	user, err := s.findUserByIdentifier(userID)
	if err != nil {
		fail(c, err)
		return
	}
	if user == nil {
		fail(c, apierror.UserNotFound(userID))
		return
	}

	p, err := parseParams(c)
	if err != nil {
		fail(c, apierror.ParameterInvalid("body"))
		return
	}

	name := p.String("name")
	if utf8.RuneCountInString(name) > 64 {
		fail(c, apierror.ParameterInvalid("name"))
		return
	}
	if name != "" {
		name = sanitizeName(name)
		if name != user.Name {
			var existing model.User
			if err := s.db.Where("name = ?", name).First(&existing).Error; err == nil {
				fail(c, apierror.UserAlreadyExists(name))
				return
			}
		}
		user.Name = name
	}

	if email := p.String("email"); email != "" {
		if !validEmail(email) || utf8.RuneCountInString(email) > 255 {
			fail(c, apierror.ParameterInvalid("email"))
			return
		}
		user.Email = &email // update path does not trim (matches PHP)
	}

	if disabled := p.BoolPtr("isDisabled"); disabled != nil {
		user.Disabled = *disabled
	}

	if err := s.db.Save(user).Error; err != nil {
		fail(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"user": s.ser.User(user)})
}
